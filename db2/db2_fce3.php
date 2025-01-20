<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ===========================================================================================> DB2 */
# ---------------------------------------------------------------------------------- ==> . konstanty
global $diety,$diety_,$jidlo_;
 $diety= array('','_bm','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
 $diety_= array(''=>'normal','_bm'=>'veget','_bl'=>'bezlep');
// bezmasá dieta na Pavlákové od roku 2017 není (ale pro Pražáky je)
//$diety= array('','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
//$diety_= array(''=>'normal','_bl'=>'bezlep');
$jidlo_= array('sc'=>'snídaně celá','sp'=>'snídaně dětská','oc'=>'oběd celý',
               'op'=>'oběd dětský','vc'=>'večeře celá','vp'=>'večeře dětská');
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
          . ",o_umi,prislusnost");
  // načtení rodin
  $qr= pdo_qry("SELECT id_rodina AS key_rodina,ulice AS r_ulice,psc AS r_psc,obec AS r_obec,
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
      if ( !$o->adresa ) {
        $o->ulice= "®".$rod[$n]->r_ulice;
        $o->psc=   "®".$rod[$n]->r_psc;
        $o->obec=  "®".$rod[$n]->r_obec;
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
      $cleni.= "~";
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
/** =========================================================================================> AKCE2 */
# ------------------------------------------------------------------------------------ web prihlaska
# propojení s www.setkani.org - musí existovat popis akce s daným url
#     on=1 dovolí přihlašování přes web, on=0 je zruší
function web_prihlaska($akce,$url,$on,$garant) {  trace();
  global $answer_db;
  $html= '';
  $ok= preg_match("~(nove|akce)/(\d+)$~",$url,$m);
  if ( $ok ) {
    $idp= $m[2];
    $ida= $on ? $akce : 0;
    $ido= select('ikona','_cis',"druh='akce_garant' AND data='$garant'",$answer_db);
    if ( !$ido ) {
      $html.= "<hr>POZOR: chybí garant akce!";
      goto end;
    }
    $ok= select("uid","tx_gncase_part","uid='$idp' AND !deleted AND !hidden",'setkani');
    if ( $ok ) {
      $ok= query("UPDATE tx_gncase_part SET id_akce=$ida WHERE uid=$idp",'setkani');
      $html.= "na www.setkani.org bylo ".($on?"zapnuto":"vypnuto")." elektronické přihlašování";
    }
    else {
      $html.= "url pozvánky není v očekávaném tvaru";
    }
  }
  else {
    $html.= "url pozvánky není v očekávaném tvaru";
  }
end:  
  $html.= "<hr>DBG: $garant $ido<hr>";
  return $html;
}
# ------------------------------------------------------------------------------------- web zmena_ok
# propojení s www.setkani.org - informace resp. odsouhlasení změn po online přihlášce na akci
#  - doit=0 => generování přehledové hlášky o změnách týkající se pobytu
#  - doit=1 => nulování všech web_zmena 
function web_zmena_ok($id_pobyt,$doit=0) {  trace();
  $msg= '';
  list($dp,$i0r)= select('web_zmena,i0_rodina','pobyt',"id_pobyt=$id_pobyt");
  list($zr,$dr)= !$i0r ? 0 : select('COUNT(*),web_zmena',
      'rodina',"id_rodina=$i0r && web_zmena!='0000-00-00'");
  list($zs,$ds,$ks)= select('COUNT(*),MAX(spolu.web_zmena),GROUP_CONCAT(id_spolu)',
      'spolu',"id_pobyt=$id_pobyt && web_zmena!='0000-00-00'");
  list($zo,$do,$ko)= select('COUNT(*),MAX(osoba.web_zmena),GROUP_CONCAT(id_osoba)',
      'spolu JOIN osoba USING (id_osoba)',"id_pobyt=$id_pobyt && osoba.web_zmena!='0000-00-00'");
  if ( !$doit ) {
    // vypsání informací o webovém přihlášení
    $d= max($dp,$dr,$ds,$do);
//    $msg= "p:$dp r:$zr $dr s:$zs $ds o:$zo $do<hr>";
    if ( $d!='0000-00-00' || $zs || $zo ) {
      $day= sql_date1($d);
      $n_ucastniku= kolik_1_2_5($zs,"účastníka,účastníky,účastníků");
      $k_ucastnika= kolik_1_2_5($zo,"účastníka,účastníků,účastníků");
      $msg.= ( !$dp ? '' : "Dne $day byla na webu vyplněna online přihláška")
          .  ( !$zr ? ' bez změny rodinného údaje' : " se změnou rodinného údaje")
          .  ( !$zs ? '' : " přihlašující $n_ucastniku")
          .  ( !$zo ? '' : " z toho u $k_ucastnika se změnou osobních údajů")
          . ".<br>";
    }
  }
  else {
    // odstranění příznaků webového přihlášení
    if ( $dp ) {
      ezer_qry("UPDATE",'pobyt',$id_pobyt,array((object)
          array('fld'=>'web_zmena','op'=>'u','val'=>'0000-00-00')));
    }
    if ( $i0r ) {
      ezer_qry("UPDATE",'rodina',$i0r,array((object)
          array('fld'=>'web_zmena','op'=>'u','val'=>'0000-00-00')));
    }
    if ( $zs ) {
      foreach(explode(',',$ks) as $ids ) {
        ezer_qry("UPDATE",'spolu',$ids,array((object)
            array('fld'=>'web_zmena','op'=>'u','val'=>'0000-00-00')));
      }
    }
    if ( $zo ) {
      foreach(explode(',',$ko) as $ido ) {
        ezer_qry("UPDATE",'osoba',$ido,array((object)
            array('fld'=>'web_zmena','op'=>'u','val'=>'0000-00-00')));
      }
    }
    $msg= "s ok";
  }
  return $msg;
}
# --------------------------------------------------------------------------------------- akce2 mapa
# získání seznamu souřadnic bydlišť účastníků akce 
# s případným filtrem - např. bez pečounů: pobyt.funkce!=99
function akce2_mapa($akce,$filtr='') {  trace();
  global $ezer_version;
  // dotaz
  $psc= $obec= array();
  $AND= $filtr ? " AND $filtr" : '';
  $qo=  "
    SELECT prijmeni,adresa,psc,obec,
      (SELECT MIN(CONCAT(role,RPAD(psc,5,' '),'x',obec))
       FROM tvori AS ot JOIN rodina AS r USING (id_rodina)
       WHERE ot.id_osoba=o.id_osoba 
      ) AS r_psc
    FROM pobyt
    JOIN spolu USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_akce='$akce' $AND
    GROUP BY id_osoba
    ";
  // najdeme použitá PSČ
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    $p= $o->adresa ? $o->psc : substr($o->r_psc,1,5);
    $m= $o->adresa ? $o->obec : substr($o->r_psc,7);
    $psc[$p].= "$o->prijmeni ";
    $obec[$p]= $obec[$p] ?: $m;
  }
//                                         debug($psc);
  $icon= "./ezer$ezer_version/client/img/circle_gold_15x15.png,7,7";
  return mapa2_psc($psc,$obec,0,$icon); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# --------------------------------------------------------------------------------------- akce2 info
# rozšířené informace o akci
# text=1 v textové verzi, text=0 jako objekt s počty
# pobyty=1 pokud chceme jména pro přehledové tabulky - má smysl jen pro text=0 - potom
#   info.pobyty= [ {idp:id_pobyt, prijmeni, jmena, hnizdo:n, typ:vps|nov|rep|tym|nah, 
#                   deti:seznam věků dětí a chův - těch s §}, ...]
#   info.pecouni= [ {ids, hnizdo, prijmeni, jmeno}, ... ]
function akce2_info($id_akce,$text=1,$pobyty=1,$id_order=0) { trace(); 
  global $setkani_db;
  $nf= function($n) { return number_format($n, 0, '.', '&nbsp;'); };
  $html= '';
  $access= $uid= 0; // org_ds (64) znamená čístě jen pobyt na Domu setkání
  $info= (object)array('muzi'=>0,'zeny'=>0,'deti'=>0,'peco'=>0,'rodi'=>0,'skup'=>0,'title'=>array());
  $zpusoby= map_cis('ms_akce_platba','zkratka');  // způsob => částka
  $stavy=   map_cis('ms_platba_stav','zkratka');     // stav úhrady
//  debug($stavy,'map uhrada_stav');
  $funkce=  map_cis('ms_akce_funkce','zkratka');  // funkce na akci
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka'); // funkce pečovatele na akci
  $bad= "<b style='color:red'>!!!</b>";
  $uhrady= $uhrady_d= $fces= $pob= array();
  $pfces= array_fill(0,9,0);
  $celkem= $uhrady_celkem= $uhrady_odhlasenych= $uhradit_celkem= $vratit_celkem= 0;
  $vraceno= $dotace_celkem= $dary_celkem= 0;
  $aviz= 0;
  $_hnizda= '';  $hnizda= array(); $hnizdo= array(); $a_bez_hnizd= 1;
  $soubeh= 0; // hlavní nebo souběžná akce
  $pro_pary= 0;
  if ( $id_akce ) {
    $ucasti= $rodiny= $dosp= $muzi= $mrop= $zeny= $chuvy_d= $chuvy_p= $deti= $pecounu= $pp= $po= $pg= $web= 0;
    $vic_ucasti= $divna_rodina= '';
    $err= $err2= $err3= $err4= $err_h= $err_r= 0;
    $odhlaseni= $neprijati= $neprijeli= $nahradnici= $nahradnici_osoby= 0;
    $akce= $chybi_nar= $chybi_sex= '';
    // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
    $web_online= 0;     // web_changes>0
    $web_novi= 0;       // web_changes&4
    $web_kalendar= $web_obsazeno= $web_anotace= $web_url= '';
    // pro akce typu MS budeme podrobnější (a delší)
    list($druh,$a_hnizda)= select('druh,hnizda','akce',"id_duakce=$id_akce");
    $a_hnizda= explode(',',$a_hnizda);
//                          debug($a_hnizda,"hnízda podle akce: ");
    $a_bez_hnizd= count($a_hnizda)==1;
    $akce_ms= $druh==1||$druh==2 ? 1 : 0;
    // zjistíme hodnoty hnízd u účastníků a 
    // pokud akce není v hnízdech simulujeme je - kvůli opravám (mohla být plánována do hnízd)
    $p_hnizda= select('GROUP_CONCAT(DISTINCT hnizdo ORDER BY hnizdo)','pobyt',"id_akce='$id_akce'");
    $p_hnizda= explode(',',$p_hnizda);
//                          debug($p_hnizda,"hnízda podle pobytů: ");
    if (count($a_hnizda)!=count($p_hnizda)) {
      $err_h++;
    }
    // zjistíme násobnou přítomnost osob (jako detekci chyby)
    $rn= pdo_qry("
      SELECT COUNT(DISTINCT id_pobyt) AS _n,MIN(funkce) AS _f1,MAX(funkce) AS _f2,prijmeni,jmeno
      FROM spolu AS s
      LEFT JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN osoba AS o USING (id_osoba)
      WHERE id_akce='$id_akce'
      GROUP BY id_osoba HAVING _n>1
    ");
    while ( $rn && (list($n,$f1,$f2,$prijmeni,$jmeno)= pdo_fetch_row($rn)) ) {
      $err3++;
      $vic_ucasti.= ", $jmeno $prijmeni";
      if ( $f1<99 && $f2==99 ) $vic_ucasti.= " (jako dítě a jako pečovatel)";
    }
    // pokud chceme jména pro přehledové tabulky - má smysl jen pro tisk=0
    $fld_pobyty= $pobyty
        ? "IFNULL(r.nazev,o.prijmeni) AS _prijmeni,
           IF(ISNULL(r.id_rodina),o.jmeno,GROUP_CONCAT(IF(t.role IN ('a','b'),
             o.jmeno,'') ORDER BY t.role SEPARATOR ' ')) AS _jmena,
           IF(ISNULL(r.id_rodina),'',GROUP_CONCAT(IF(t.role NOT IN ('a','b'),CONCAT(
               IF(s.s_role=5 AND t.role='d','*',''),
               IF(s.s_role=5 AND t.role='p','§',''),
               YEAR(a.datum_od)-YEAR(o.narozeni)-(DATE_FORMAT(a.datum_od,'%m%d')<DATE_FORMAT(o.narozeni,'%m%d')))
             ,'') ORDER BY o.narozeni DESC)) AS _vekdeti"
        : '1';
    // projdeme pobyty
    $fce_neucast= select('GROUP_CONCAT(data)','_cis',"druh='ms_akce_funkce' AND ikona=1");
    $ms_ucasti= $akce_ms
        ? "r_ms+( SELECT COUNT(*) FROM pobyt AS xp JOIN akce AS xa ON xp.id_akce=xa.id_duakce 
             WHERE xp.i0_rodina=p.i0_rodina AND xa.druh=1 AND zruseno=0
               AND xp.id_pobyt!=p.id_pobyt AND xp.funkce NOT IN ($fce_neucast)) AS _ucasti_ms"
        : '0 AS _ucasti_ms';
    $JOIN_tvori= $pobyty 
        ? "LEFT JOIN tvori AS t USING (id_osoba,id_rodina)" : '';
    $JOIN_rodina= $akce_ms || $pobyty
        ? "LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina" : '';
    $fld_dum= $setkani_db ? 'uid,' : '';
    $JOIN_dum= $setkani_db ? "LEFT JOIN $setkani_db.tx_gnalberice_order AS d ON d.id_akce=a.id_duakce" : '';
    $qry= "SELECT $fld_dum a.access, a.nazev, a.datum_od, a.datum_do, now() as _ted,i0_rodina,funkce,p.web_zmena,web_changes,
             COUNT(id_spolu) AS _clenu,IF(c.ikona=2,1,0) AS _pro_pary,a.hnizda,p.hnizdo,
         --  SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)<18,1,0)) AS _deti,
             SUM(IF(t.role='d',1,0)) AS _deti,
             SUM(IF(t.role='b',1,0)) AS _manzelky, SUM(IF(t.role='a',1,0)) AS _manzele,
             r.nazev AS _nazev_rodiny,
             SUM(IF(CEIL(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)))<=3,1,0)) AS _kocar,
             SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)>=18 AND sex=1,1,0)) AS _muzu,
             SUM(IF(iniciace>0,1,0)) AS _mrop,
             SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)>=18 AND sex=2,1,0)) AS _zen,
         --  SUM(IF(s.s_role=5 AND s.pfunkce=0,1,0)) AS _chuv,
             SUM(IF(s.s_role=5 AND s.pfunkce=0 AND t.role='d',1,0)) AS _chuv_d,
             SUM(IF(s.s_role=5 AND s.pfunkce=0 AND t.role='p',1,0)) AS _chuv_p,
             SUM(IF(s.s_role=5 AND s.pfunkce=5,1,0)) AS _po,
             SUM(IF(s.s_role=4 AND s.pfunkce=4,1,0)) AS _pp,
             SUM(IF(               s.pfunkce=8,1,0)) AS _pg,
             SUM(IF(s.pfunkce=1,1,0)) AS _p1,
             SUM(IF(s.pfunkce=2,1,0)) AS _p2,
             SUM(IF(s.pfunkce=3,1,0)) AS _p3,
             SUM(IF(s.pfunkce=4,1,0)) AS _p4,
             SUM(IF(s.pfunkce=5,1,0)) AS _p5,
             SUM(IF(s.pfunkce=6,1,0)) AS _p6,
             SUM(IF(s.pfunkce=7,1,0)) AS _p7,
             SUM(IF(s.pfunkce=8,1,0)) AS _p8,
             SUM(IF(o.narozeni='0000-00-00',1,0)) AS _err,
             GROUP_CONCAT(IF(o.narozeni='0000-00-00',CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo,
             SUM(IF(o.sex NOT IN (1,2),1,0)) AS _err2,
             GROUP_CONCAT(IF(o.sex NOT IN (1,2),CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo2,
             -- avizo,platba,datplatby,zpusobplat,
             p.platba1+p.platba2+p.platba3+p.platba4 AS _platit,
             vratka1+vratka2+vratka3+vratka4 AS _vratit,
             p.platba4-vratka4 AS _dotace,
             IFNULL(sa.id_duakce,0) AS _soubezna, a.id_hlavni AS _hlavni,
             a.web_kalendar,a.web_anotace, a.web_url, a.web_obsazeno, $ms_ucasti,
             p.id_pobyt,$fld_pobyty
           FROM akce AS a
           LEFT JOIN akce AS sa ON sa.id_hlavni=a.id_duakce
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
           JOIN osoba AS o USING (id_osoba) -- ON s.id_osoba=o.id_osoba
           $JOIN_rodina
           $JOIN_tvori
           LEFT JOIN _cis AS c ON c.druh='ms_akce_typ' AND c.data=a.druh
           $JOIN_dum
           WHERE a.id_duakce='$id_akce' AND o.deleted=''
             -- AND p.id_pobyt IN (59240,59318)
           GROUP BY p.id_pobyt";
    $res= pdo_qry($qry);
    while ( $res && $p= pdo_fetch_object($res) ) {
      $access= $p->access;
      $uid= $p->uid;
      $pobyt= null;
      // diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
      $soubeh= $p->_soubezna ? 1 : ( $p->_hlavni ? 2 : 0);
      $fce= $p->funkce;
      if ($pobyty && !in_array($fce,array(10,13,14,99)))
        $pobyt= (object)array('idp'=>$p->id_pobyt,'prijmeni'=>$p->_prijmeni,'jmena'=>$p->_jmena,
            'deti'=>preg_replace("~,,~",',',preg_replace("~^,*|,*$~",'',$p->_vekdeti)),
            'hnizdo'=>$p->hnizdo, 'fce'=>$fce);
      $pro_pary= $p->_pro_pary;
      $web_kalendar= $p->web_kalendar;
      $web_anotace= $p->web_anotace;
      $web_obsazeno= $p->web_obsazeno;
      $web_url= $p->web_url;
      // kontrola rolí
      if ($p->_manzelky>1 || $p->_manzele>1) {
        $divna_rodina.= ", $p->_nazev_rodiny";
        $err_r++;
      }
      // počty v hnízdech
      $hn= $a_bez_hnizd ? 1 : $p->hnizdo;
      if (!isset($hnizdo[$hn]))
        $hnizdo[$hn]= array('vps'=>0, 'nov'=>0, 'rep'=>0, 'nah'=>0,
            'pec'=>0, 'det'=>0, 'koc'=>0, 'dos'=>0, 'chu_d'=>0, 'chu_p'=>0);
      if (!in_array($fce,array(10,13,14,15,99))) { // 15+14+10+13 neúčast, 99 pečouni, 9 náhradník
        $hnizdo[$hn]['vps']+= ($fce==1||$fce==2) ? 1 : 0;
        $hnizdo[$hn]['nov']+= $fce==0 && $p->_ucasti_ms==0 ? 1 : 0;
        $hnizdo[$hn]['rep']+= $fce==0 && $p->_ucasti_ms>0 ? 1 : 0;
        $hnizdo[$hn]['dos']+= $p->_muzu+$p->_zen;
        $hnizdo[$hn]['chu_d']+= $p->_chuv_d;
        $hnizdo[$hn]['chu_p']+= $p->_chuv_p;
        $hnizdo[$hn]['det']+= $p->_deti;
        $hnizdo[$hn]['koc']+= $p->_kocar;
        $hnizdo[$hn]['nah']+= $fce==9 ? 1 : 0;
        if ($pobyty && $pobyt!==null) {
          $pobyt->typ= ($fce==1||$fce==2) ? 'vps' : ($fce==0 
              ? ($p->_ucasti_ms==0 ? 'nov' : 'rep') : ($fce==9 ? 'nah' : 'tym'));
        }
      }
      // online přihlášky
      if ( $p->web_zmena!='0000-00-00' || $p->web_changes ) {
        $web++;
        if ( $p->web_changes )   $web_online++;
        if ( $p->web_changes&4 ) $web_novi++;
      }
      // sčítání úhrad 
      $uhradit= $p->_platit - $p->_vratit;
      $vratit_celkem+= $p->_vratit;
      $uhradit_celkem+= $uhradit;
      $dotace_celkem+= $p->_dotace;
      $zaplaceno= 0;
      $ru= pdo_qry("SELECT u_castka,u_zpusob,u_stav,u_za FROM uhrada WHERE id_pobyt=$p->id_pobyt");
      while ( $ru && list($u_castka,$u_zpusob,$u_stav,$u_za)= pdo_fetch_row($ru) ) {
        $zaplaceno+= $u_castka;
        if ($u_za) {
          if (!isset($uhrady_d[$u_zpusob][$u_stav])) $uhrady_d[$u_zpusob][$u_stav]= 0;
          $uhrady_d[$u_zpusob][$u_stav]+= $u_castka;
        }
        else {
          if (!isset($uhrady[$u_zpusob][$u_stav])) $uhrady[$u_zpusob][$u_stav]= 0;
          $uhrady[$u_zpusob][$u_stav]+= $u_castka;
        }
        $uhrady_celkem+= $u_castka;
        if (in_array($fce,array(9,10,13,14,15) )) 
          $uhrady_odhlasenych+= $u_castka;
        if ($u_stav==4) 
          $vraceno+= $u_castka;
      }
      if ($uhradit)
        $dary_celkem+= $zaplaceno > $uhradit ? $zaplaceno - $uhradit : 0;
//      // záznam plateb
//      if ( $p->platba ) {
//        $celkem+= $p->platba;
//        $platby[$p->zpusobplat]+= $p->platba;
//      }
//      if ( $p->avizo ) {
//        $aviz++;
//      }
      // diskuse funkce=odhlášen/14 a funkce=nepřijel/10 a funkce=náhradník/9 a nepřijat/13
      if ( in_array($fce,array(9,10,13,14,15) ) ) {
        $neprijati+= $fce==13 ? 1 : 0;
        $odhlaseni+= $fce==14 ? 1 : 0;
        $neprijeli+= $fce==10 ? 1 : 0;
        $nahradnici+= $fce==9 ? 1 : 0;
        $nahradnici_osoby+= $fce==9 ? $p->_clenu : 0;
      }
      // diskuse pečouni
      else if ( $fce==99 ) {
//        $hnizdo[$hn]['pec']+= $p->_clenu;
        $pecounu+= $p->_clenu;
        $pfces[1]+= $p->_p1;
        $pfces[2]+= $p->_p2;
        $pfces[3]+= $p->_p3;
        $pfces[4]+= $p->_p4;
        $pfces[5]+= $p->_p5;
        $pfces[6]+= $p->_p6;
        $pfces[7]+= $p->_p7;
        $pfces[8]+= $p->_p8;
        // pro akci v hnízdech zjisti strukturu pečounů
//        if ( $p->hnizda ) {
          $info->pecouni= array();
          $rp= pdo_qry("
            SELECT id_spolu,s_hnizdo,jmeno,prijmeni FROM spolu JOIN osoba USING (id_osoba)
            WHERE id_pobyt=$p->id_pobyt 
          ");
          while ( $rp && (list($ids,$s_hnizdo,$jmeno,$prijmeni)= pdo_fetch_row($rp)) ) {
            $info->pecouni[]= (object)array('ids'=>$ids,
                'hnizdo'=>$s_hnizdo,'prijmeni'=>$prijmeni,'jmeno'=>$jmeno);
            $s_hnizdo= $a_bez_hnizd ? 1 : $s_hnizdo;
            $hnizdo[$s_hnizdo]['pec']++;
          }
//        }
      }
      else if ( !in_array($fce,array(0,1,2,5) ) ) {
        if (!isset($fces[$fce])) $fces[$fce]= 0;
        $fces[$fce]++;
        $muzi+= $p->_muzu;
        $mrop+= $p->_mrop;
        $zeny+= $p->_zen;
      }
      else {
        // údaje účastníků jednoho pobytu
        $ucasti++;
        $muzi+= $p->_muzu;
        $mrop+= $p->_mrop;
        $zeny+= $p->_zen;
        $chuvy_d+= $p->_chuv_d;
        $chuvy_p+= $p->_chuv_p;
        $pp+= $p->_pp;
        $po+= $p->_po;
        $pg+= $p->_pg;
        $deti+= $p->_deti;
        $err+= $p->_err;
        $err2+= $p->_err2;
        $rodiny+= $p->i0_rodina && $p->_clenu>1 ? 1 : 0;
        $chybi_nar.= $p->_kdo;
        $chybi_sex.= $p->_kdo2;
      }
      // údaje akce
      $akce= $p->nazev;
      $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
      $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
      $od= sql_date1($p->datum_od);
      $do= sql_date1($p->datum_do);
      $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
      if ($akce_ms && !$p->hnizda && !$hnizda) {
        // simulace hnízd
        $hnizda= array(1); 
        $info->hnizda= $hnizda;
      }
      elseif ( $p->hnizda && !$hnizda ) {
        $hnizda= explode(',',$p->hnizda);
        $n_h= count($hnizda);
        $_hnizda= " <b>ve $n_h hnízdech</b>";
        array_unshift($hnizda,"<i>nezařazeno</i>"); 
        $info->hnizda= $hnizda;
      }
      // pokud se chtějí pobyty
      if ($pobyty && $pobyt) {
        $pob[]= $pobyt;
      }
    }
    if ( $chybi_nar ) $chybi_nar= substr($chybi_nar,2);
    if ( $chybi_sex ) $chybi_sex= substr($chybi_sex,2);
    if ( $vic_ucasti ) $vic_ucasti= substr($vic_ucasti,2);
    if ( $divna_rodina ) $divna_rodina= substr($divna_rodina,2);
    display("dicná rodina: $divna_rodina");
    $dosp+= $muzi + $zeny;
                              display("$dosp+= $muzi + $zeny");
    $skupin= $ucasti;
//    if ( $text ) {
      // čeština
      $_skupin=    je_1_2_5($skupin,"skupina,skupiny,skupin");
      $_pecounu=   je_1_2_5($pecounu,"pečoun,pečouni,pečounů");
      $_pp=        je_1_2_5($pp,"pom.pečoun,pom.pečouni,pom.pečounů");
      $_po=        je_1_2_5($po,"os.pečoun,os.pečouni,os.pečounů");
      $_pg=        je_1_2_5($pg,"člen G,členi G,členů G");
      $_dospelych= je_1_2_5($dosp,"dospělý,dospělí,dospělých");
      $_muzu=      je_1_2_5($muzi,"muž,muži,mužů");
      $_iniciovani=je_1_2_5($mrop,"iniciovaný muž,iniciovaní muži,iniciovaných mužů");
      $_zen=       je_1_2_5($zeny,"žena,ženy,žen");
      $_chuv_d=    je_1_2_5($chuvy_d,"chůva,chůvy,chův");
      $_chuv_p=    je_1_2_5($chuvy_p,"chůva,chůvy,chův");
      $_deti=      je_1_2_5($deti,"dítě,děti,dětí");
      $_osob=      je_1_2_5($dosp+$deti,"osoba,osoby,osob");
      $_err=       je_1_2_5($err,"osoby,osob,osob");
      $_err2=      je_1_2_5($err2,"osoby,osob,osob");
      $_err3=      je_1_2_5($err3,"osoba je,osoby jsou,osob je");
      $_rodiny=    je_1_2_5($rodiny,"rodina,rodiny,rodin");
      $_pobyt_o=   je_1_2_5($odhlaseni,"pobyt,pobyty,pobytů");
      $_pobyt_r=   je_1_2_5($neprijati,"přihláška,přihlášky,přihlášek");
      $_pobyt_x=   je_1_2_5($neprijeli,"pobyt,pobyty,pobytů");
      $_pobyt_n=   je_1_2_5($nahradnici,"přihláška,přihlášky,přihlášek");
      $_pobyt_no=  je_1_2_5($nahradnici_osoby,"osoba,osoby,osob");
      $_aviz=      je_1_2_5($aviz,"avízo,avíza,avíz");
      $_web_onln=  je_1_2_5($web_online,"pobyt,pobyty,pobytů");
      $_web_novi=  je_1_2_5($web_novi,"nová osoba,nové osoby,nových osob");
      // sloužili
      $sluzba= ''; $del= '<br>a dále '; 
      foreach ($fces as $f=>$fn) {
        $sluzba.= "$del $fn x ".$funkce[$f];
        $del= " a ";
      }
      // typy pečounů
      $sluzba= ''; $del= ' z toho '; 
      foreach ($pfces as $f=>$fn) { if ( $fn ) {
        $sluzba.= "$del $fn x ".$pfunkce[$f];
        $del= " a ";
      }}
      $sluzba2= ''; $del= ''; 
      if ( $pp ) { $sluzba2.= "$del $_pp"; $del= ' a '; }
      if ( $po ) { $sluzba2.= "$del $_po"; $del= ' a '; }
      if ( $pg ) { $sluzba2.= "$del $_pg"; $del= ' a '; }
      // jsou děti pečouni ok?
      $prehled= akce2_text_prehled($id_akce,'');
      if ( $prehled->pozor ) $err4++; 
      // html
      $html= $dosp+$deti>0
       ? $html.= "<h3 style='margin:0px 0px 3px 0px;'>$akce</h3>akce $cas1 $dne $_hnizda<br><hr>$cas2"
       . ($skupin ? " $_skupin účastníků"
           .($rodiny ? ($rodiny==$ucasti ? " (všechny jako rodiny)" : " (z toho $_rodiny)") :''):'')
       . ($pecounu ? " ".($skupin?" a ":'')."$_pecounu" : '')
       . $sluzba
       . ($pp+$po+$pg ? " + do skupiny pečounů patří navíc $sluzba2" : '')
       . ",<br>v počtu $_dospelych ($_muzu, $_zen)"
       . ($chuvy_d ? " a $_deti (z toho $_chuv_d)," : " a $_deti")
       . ($chuvy_p ? " a $_chuv_p dospělých," : '')
       . "<br><b>celkem $_osob</b>"
      . ( $web_online ? "<hr>přihlášky z webu: $_web_onln".($web_novi ? ", z toho $_web_novi" : '') : '')
       : "Akce byla vložena do databáze ale nemá zatím žádné účastníky";
      if ( $akce_ms && ($_hnizda || $a_bez_hnizd) ) {
        $html.= (isset($p->hnizda) && $p->hnizda ? ", takto rozřazených do hnízd" : "") . " - stiskem zobraz
          <button onclick=\"Ezer.fce.href('akce2.lst.ukaz_hnizda/$id_akce')\">
          jmenný seznam</button>";
        $html.= $a_bez_hnizd ? '' : "<ul>";
//                                                                debug($hnizda); 
//                                                                debug($hnizdo); 
        for ($h= 0; $h<count($hnizda); $h++) {
          $hn= $a_bez_hnizd ? 1 : ($h+1 % count($hnizda)-1);
          // počty a odhad
          $n_vps= $hnizdo[$hn]['vps'];
          $n_nov= $hnizdo[$hn]['nov'];
          $n_rep= $hnizdo[$hn]['rep'];
          $n_dos= $hnizdo[$hn]['dos'];
          // úvaha o pečounech: potřebujeme na 1 kočárek = 1 pečoun; na 3 nekočárky = 1 pečoun
          $n_det= $hnizdo[$hn]['det'];
          $n_koc= $hnizdo[$hn]['koc'];
          $n_chu_d= $hnizdo[$hn]['chu_d'];
          $n_chu_p= $hnizdo[$hn]['chu_p'];
          $n_pec= $hnizdo[$hn]['pec'];
          // chůvy napřed spotřebujeme na kočárky
          $koc_chu= min($n_koc,$n_pec);
          $koc= $n_koc-$koc_chu;
          $chu_navic= $n_chu_d + $n_chu_p - $koc_chu;
          $det= $n_det-$koc_chu; // neohlídaných dětí
          $nekoc= $det-$koc; // dětí bez kočárku
          $pec_odhad= $koc + round($nekoc/3) - $chu_navic;
          $x_pec= $pec_odhad>$n_pec ? $pec_odhad-$n_pec : 0;
          // počty česky
          $n_nov= je_1_2_5($n_nov,"nováček,nováčci,nováčků");
          $n_rep= je_1_2_5($n_rep,"repetent,repetenti,repetentů");
          $n_pec= $n_pec ? 'a '.je_1_2_5($n_pec,"pečoun,pečouni,pečounů") : '';
          $x_pec= $x_pec ? '(chybí asi '.je_1_2_5($x_pec,"pečoun,pečouni,pečounů").')' : '';
          $n_dos= je_1_2_5($n_dos,"dospělý,dospělí,dospělých");
          $n_det= $n_det!==''
              ? 'a '.je_1_2_5($n_det,"dítě,děti,dětí")
                .($n_chu_d ? " (z toho ".je_1_2_5($n_chu_d,"chůva,chůvy,chův").')' : '')
              : '';
          $n_dos= je_1_2_5($n_dos,"dospělý,dospělí,dospělých");
          // text
          $hniz= $h ? "<b>$hnizda[$h]</b>" : $hnizda[$h];
          $h_text= ($a_bez_hnizd ? '' : "$hniz - ")."$n_dos $n_det $n_pec $x_pec";
          if ($pobyty) {
            $info->title[$h]= "$h_text";
          }
          $html.= $a_bez_hnizd ? '<br>' : "<li>";
          $html.= $h_text;
          $html.= "<br>páry: $n_vps VPS, $n_nov, $n_rep";
        }
        $html.= $a_bez_hnizd ? '' : "</ul>";
      }
      if ( $odhlaseni + $neprijati + $neprijeli + $nahradnici > 0 ) {
        $html.= "<br><hr>";
        $msg= array();
        if ( $neprijati ) $msg[]= "nepřijato: $_pobyt_r";
        if ( $odhlaseni ) $msg[]= "odhlášeno: $_pobyt_o (bez storna)";
        if ( $neprijeli ) $msg[]= "zrušeno: $_pobyt_x (nepřijeli, aplikovat storno)";
        if ( $nahradnici ) $msg[]= "náhradníci: $_pobyt_n, celkem $_pobyt_no";
        $html.= implode('<br>',$msg);
      }
      if ( $err + $err2 + $err3 + $err4 + $err_h + $err_r > 0 ) {
        $html.= "<br><hr><b style='color:red'>POZOR:</b> ";
        $html.= $err_h>0 ? "<br>nesouhlasí počet hnízd v definici akce a v pobytech" : '';
        $html.= $err>0  ? "<br>u $_err chybí datum narození: <i>$chybi_nar</i>" : '';
        $html.= $err2>0 ? "<br>u $_err2 chybí údaj muž/žena: <i>$chybi_sex</i>" : '';
        $html.= $err_r>0 ? "<br>rodiny $divna_rodina jsou divné" : '';
        $html.= $err3>0 ? "<br>$bad $_err3 na akci vícekrát: <i>$vic_ucasti</i>" : '';
        $html.= $err4>0 ? "<br>{$prehled->pozor} (po nastavení akce jako pracovní kliknutí na odkaz ukáže rodinu)" : '';
        $html.= "<br><br>(kvůli nesprávným údajům budou počty, výpisy a statistiky také nesprávné)";
      }
      $html.= $deti ? "<hr>Poznámka: jako děti se počítají osoby, které v době zahájení akce nemají 18 let" : '';
      $html.= $mrop ? ", na akci je $_iniciovani." : '';
      if ( $pro_pary ) {
        $html.= akce2_info_par($id_akce);
      }
      // kalendář na webu
      if ( $web_kalendar ) {
        $odkaz= $web_url ? "na <a href='$web_url' target='web'>pozvánku</a>" : ' na home-page';
        $html.= "<hr><i class='fa fa-calendar-check-o'></i> 
            Akce $cas1 zobrazena v kalendáři <b>chlapi.online</b>
            s odkazem $odkaz";
        if ( $web_obsazeno )
          $html.= "<br> &nbsp;&nbsp; &nbsp; jako obsazená";
        if ( $web_anotace ) 
          $html.= "<hr>$web_anotace";
      }
//    }
//    else {
      $info->muzi= $muzi;
      $info->zeny= $zeny;
      $info->deti= $deti;
      $info->peco= $pecounu;
      $info->rodi= $rodiny;
      $info->skup= $skupin;
      $info->_pp=  $pp;
      $info->_po=  $po;
      $info->_pg=  $pg;
//    }
    // zobrazení přehledu plateb
//    debug($uhrady,"úhrady celkem $uhrady_celkem");
    $help= '';
    if ( /*!$soubeh &&*/ ($uhrady_celkem || $uhradit_celkem) ) {
      $st= "style='border-top:1px solid black'";
      if ($dary_celkem)
        $help.= "Hodnota darů bude správně až budou vráceny storna a přeplatky.<br>";
      $tab= $av= '';
      foreach ($stavy as $j=>$stav) {
        foreach ($zpusoby as $i=>$zpusob) {
          if ( isset($uhrady[$i][$j]) && $uhrady[$i][$j] )
            $tab.= "<tr><td>$stav $zpusob</td><td align='right'>".$nf($uhrady[$i][$j])."</td></tr>";
          if ( isset($uhrady_d[$i][$j]) && $uhrady_d[$i][$j] )
            $tab.= "<tr><td>$stav za děti $zpusob</td><td align='right'>".$nf($uhrady_d[$i][$j])."</td></tr>";
        }
      }
      display("vrátit=$vratit_celkem, vráceno=$vraceno");
      if ($vratit_celkem!=-$vraceno) 
        fce_warning("má být vráceno $vratit_celkem ale bankou bylo vráceno=$vraceno");
      if ($vratit_celkem && $vratit_celkem!=-$vraceno)
        $help.= "<br>Správnost vratek za storna je třeba kontrolovat v <b>Platba za akci</b>";
      else
        $help.= "<br>... nyní se to zdá být v pořádku (předpisy storna se rovnají jejich platbám)";
      if ($uhradit_celkem) {
        $bilance= $uhrady_celkem - $uhradit_celkem;
        $bilance_slev= $dary_celkem + $dotace_celkem;
        $sgn1= $bilance>0 ? '+' : '';
        $sgn2= $bilance_slev>0 ? '+' : '';
        $sgn3= $uhrady_odhlasenych>0 ? '+' : '';
        $av= $aviz ? "<td $st> a $_aviz platby</td>" : '<td></td>';
        $tab.= "<tr><td $st>ZAPLACENO</td><td align='right' $st><b>".$nf($uhrady_celkem)."</b></td>$av
          <td>dary</td><td align='right'>".$nf($dary_celkem)."</td></tr>";
        $tab.= "<tr><td>požadováno</td><td align='right'><b>".$nf($uhradit_celkem)."</b></td><td></td>
          <td>slevy</td><td align='right'>".$nf($dotace_celkem)."</td></tr>";
        $tab.= "<tr><td>rozdíl</td><td align='right' $st>$sgn1".$nf($bilance)."</td><td></td>
          <td>bilance</td><td align='right' $st><b>$sgn2".$nf($bilance_slev)."</b></td></tr>";
        if ($uhrady_odhlasenych) 
          $tab.= "<tr><td>(v tom odhlášení atp.</td><td align='right'>$sgn3".$nf($uhrady_odhlasenych)."</td>
            <td>)</td><td></td><td></td></tr>";
      }
      else {
        $tab.= "<tr><td $st>ZAPLACENO</td><td align='right' $st><b>".$nf($uhrady_celkem)."</b></td>$av</tr>";
      }
      if ($soubeh)
        $help.= "<br><br>Pro akce s tzv. souběhem to bude chtít výsledek konzultovat s Carlosem :-)";
      $html.= "<hr style='clear:both;'>
        <div style='float:right;width:150px'><i>$help</i></div>
        <h3 style='margin-bottom:3px;'>Přehled plateb za akci (z karty Účastníci)</h3>
        <table>$tab</table>
        <i>
          <br>'ZAPLACENO' se počítá z pravého sloupce karty Účastníci/Platby za akci
          <br>'požadováno' je částka požadovaná po účastnících po odpočtu vratek
          <br>'rozdíl' je hrubý rozdíl (obsahuje i nevrácené platby odhlášených atp.)
          <br>'dary' jsou přeplatky přítomných na akci, po odečtení slev dostaneme 'bilanci'
        </i>
          ";
    }
  }
  elseif (!$uid && !$id_order) {
    $html= "Tato akce ještě nebyla vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            na řádek s akcí";
  }
  // pokud se chtějí pobyty
  if ($pobyty) {
    $info->pobyty= $pob;
//                                                                  debug($info,"info s pobyty");
  }
  // pokud je to pobyt na Domě setkání
  if ($uid || $id_order) {
    $html= dum_objednavka_info($id_order ?: $uid,$id_akce,$html,$id_order?1:0);
  }
  return $text ? $html : $info;
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
# --------------------------------------------------------------------------- akce2 info_par
# charakteristika účastníků z hlediska páru,
# počítáme pouze v případě, když je definované i0_pobyt
function akce2_info_par($ida,$idp=0,$tab_only=0) { trace();
  $html= $tab= '';
  $typy= array(''=>0,'a'=>0,'b'=>0,'s'=>0,'as'=>0,'bs'=>0,'abs'=>0,'bas'=>0,);
  $neucasti= select1("GROUP_CONCAT(data)",'_cis',"druh='ms_akce_funkce' AND ikona=1");
  // projdeme pobyty a vybereme role 'a' a 'b' - pokud nejsou oba, nelze nic spočítat
  $cond= $idp ? "id_pobyt=$idp " : "1";
  $rp= pdo_qry("
    SELECT GROUP_CONCAT(CONCAT(t.role,s.id_osoba)) AS _par,i0_rodina,id_pobyt
    FROM pobyt AS p
    JOIN spolu AS s USING (id_pobyt)
    LEFT JOIN tvori AS t ON t.id_rodina=i0_rodina AND t.id_osoba=s.id_osoba
    WHERE funkce IN (0,1,2) AND id_akce=$ida AND $cond AND t.role IN ('a','b')
    GROUP BY id_pobyt
  ");
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    if ( !strpos($p->_par,',') ) continue;
    $par= array();
    $ids= '';
    foreach (explode(',',$p->_par) as $r_id) {
      $id= substr($r_id,1);
      $par[$id]= substr($r_id,0,1);
      $ids.= ($ids ? ',' : '').$id;
    }
//                                                 debug($par,count($par)==2);
    $typ= '';
    // probereme účasti na akcích (nepočítáme účasti < 18 let) postupně od nejstarších
//    ezer_connect('ezer_db2',true);
    $rx= pdo_qry("
      SELECT a.id_duakce as ida,p.id_pobyt as idp,
        a.datum_od,a.nazev as akce,p.funkce as fce,a.typ,a.druh,
        GROUP_CONCAT(s.id_osoba) AS _ucast
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      WHERE a.spec=0 AND zruseno=0 AND s.id_osoba IN ($ids) 
        AND YEAR(a.datum_od)-YEAR(o.narozeni)>18
        AND p.funkce NOT IN ($neucasti)
      GROUP BY id_pobyt
      ORDER BY datum_od
    ");
    while ( $rx && $x= pdo_fetch_object($rx) ) {
      // určení účasti na akci: m|z|s
      $ucast= explode(',',$x->_ucast);
      if ( count($ucast)==2 ) {
        $typ.= 's';
        break;
      }
      $ab= $par[$ucast[0]];
//                                                   display($ab);
      // doplnění do typu
      if ( strpos($typ,$ab)===false )
        $typ.= $ab;
    }
//     $html.= "<br>{$p->id_pobyt}:$typ";
    $typy[$typ]++;
  }
  if ( $tab_only ) {
    $ret= $typy;
  }
  else {
    $pocty= 0;
    $tab.= "<div><table class='stat' style='float:left;margin-right:5px;'>";
    $tab.= "<tr><th>postup účastí</th><th> párů </th></tr>";
    foreach ($typy as $typ=>$pocet) {
      $pocty+= $pocet;
      $tab.= "<tr><th>$typ</th><td>$pocet</td></tr>";
    }
    $tab.= "<hr></table>";
    $tab.= "Význam řádků s..bas
        <br>s = již první akce byla společná
        <br>as = napřed byl na nějaké akci muž, pak byli na společné
        <br>bs = napřed byla na nějaké akci žena, pak byli na společné
        <br>abs = napřed byl muž, potom žena, pak společně
        <br>bas = napřed byla žena, potom muž, pak společně
    </div>";
    if ( $pocty ) {
      $html.= $tab;
    }
    $ret= $html;
  }
//                                                 debug($typy);
  return $ret;
}
# --------------------------------------------------------------------------------------- akce2 id2a
# vrácení hodnot akce
function akce2_id2a($id_akce) {  //trace();
  global $USER;
  $a= (object)array('title'=>'?','cenik'=>0,'cena'=>0,'soubeh'=>0,'hlavni'=>0,'soubezna'=>0);
  list($a->title,$a->rok,$a->cenik,$a->cenik_verze,$a->cena,$a->hlavni,$a->soubezna,$a->org,
      $a->ms,$a->druh,$a->hnizda,$a->web_wordpress,$a->mezinarodni,$poradatel,$a->tym,
      $a->datum_od, $a->datum_do, $a->strava_oddo)=
    select("a.nazev,YEAR(a.datum_od),a.ma_cenik,a.ma_cenik_verze,a.cena,a.id_hlavni,"
      . "IFNULL(s.id_duakce,0),a.access,IF(a.druh IN (1,2),1,0),a.druh,a.hnizda,a.web_wordpress,"
      . "a.mezinarodni,a.poradatel,a.tym,a.datum_od,a.datum_do,a.strava_oddo",
      "akce AS a
       LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$id_akce");
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  $a->soubeh= $a->soubezna ? 1 : ( $a->hlavni ? 2 : 0);
  $a->rok= $a->rok ?: date('Y');
  $a->title.= ", {$a->rok}";
  // zjištění, zda uživatel je 1) garantem 2) garantem této akce
  $a->garant= 0;
  $skills= explode(' ',$USER->skills);
  if (in_array('g',$skills)) { // g-uživatel musí mít v číselníku akce_garant svoje id_user
    $idu= $USER->id_user;
    $data= select('data','_cis',"druh='akce_garant' AND ikona='$idu'");
    if ($data) {
      $a->garant= $poradatel==$data ? 2 : 1;
    }
  }
  // doplnění případného pobytu v Domě setkání
  global $setkani_db;
  if ($setkani_db && ($a->org==1 || $a->org & org_ds)) {
//  if ($setkani_db && $a->org==1) {
    $a->id_order= select('uid',"$setkani_db.tx_gnalberice_order","id_akce=$id_akce");
  }
//                                                                 debug($a,"akce $id_akce user {$USER->id_user}");
  return $a;
}
# ------------------------------------------------------------------------------ akce2 soubeh_nastav
# nastavení akce jako souběžné s jinou (která musí mít stejné datumy a místo konání)
function akce2_soubeh_nastav($id_akce,$nastavit=1) {  trace();
  $msg= "";
  if ( $nastavit ) {
    list($hlavni,$nazev,$nic,$pocet)=
      select("h.id_duakce,h.nazev,s.id_hlavni,COUNT(*)","akce AS h JOIN akce AS s ON s.misto=h.misto",
        "s.id_duakce=$id_akce AND s.id_duakce!=h.id_duakce AND s.datum_od=h.datum_od");
    if ( !$pocet ) {
      $msg= "k souběžné akci musí napřed existovat hlavní akce se stejným začátkem a místem konání";
      goto end;
    }
    elseif ( $pocet>1 ) {
      $msg= "Souběžná akce již existuje (tzn. již jsou $počet akce se stejným začátkem a místem konání)";
      goto end;
    }
    elseif ( $nic ) {
      $msg= "Tato akce již je souběžná k hlavní akci (se stejným začátkem a místem konání)";
      goto end;
    }
    // vše je v pořádku $hlavni může být hlavní akcí k $id_akce
    $ok= query("UPDATE akce SET id_hlavni=$hlavni WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce byla přiřazena k hlavní akci '$nazev'" : "CHYBA!";
  }
  else {
    // zrušit nastavení
    $ok= query("UPDATE akce SET id_hlavni=0 WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce je nadále vedena jako samostatná" : "CHYBA!";
  }
end:
  return $msg;
}
# ----------------------------------------------------------------------------- akce2 delete_confirm
# dotazy před zrušením akce
function akce2_delete_confirm($id_akce) {  trace();
  $ret= (object)array('zrusit'=>0,'ucastnici'=>'');
  // fakt zrušit?
  list($nazev,$misto,$datum)=
    select("nazev,misto,DATE_FORMAT(datum_od,'%e.%m.%Y')",'akce',"id_duakce=$id_akce");
  $ret->zrusit= "Opravdu smazat akci '$nazev, $misto' začínající $datum?";
  if ( !$nazev ) goto end;
  // má účastníky
  $ucastnici= select('COUNT(*)','pobyt',"id_akce=$id_akce");
  $pecouni= select('COUNT(*)','pobyt LEFT JOIN spolu USING (id_pobyt)',"id_akce=$id_akce AND funkce=99");
  $p= $pecouni ? " a $pecouni pečounů" : '';
  $ret->ucastnici= $ucastnici
    ? "Tato akce má již zapsáno $ucastnici účastníků$p. Mám akci označit jako zrušenou?"
    : '';
  $idd= select1('IFNULL(id_order,0)','ds_order',"id_akce=$id_akce AND deleted=0");
  if ($idd) $ret->zrusit.= "<br>Současně bude zrušena i objednávka č.$idd v Domě setkání.";
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- akce2 delete
# zrušení akce
function akce2_delete($id_akce,$ret,$jen_zrušit=0) {  trace();
  $msg2= '.';
  $idd= select1('IFNULL(id_order,0)','ds_order',"id_akce=$id_akce AND deleted=0");
  if ($idd) {
    query("UPDATE ds_order SET deleted=1,id_akce=0 WHERE id_akce=$id_akce");
    $msg2= ", objednávka č.$idd byla zrušena.";
  }
  $nazev= select("nazev",'akce',"id_duakce=$id_akce");
  if ($jen_zrušit) {
    query("UPDATE akce SET zruseno=1 WHERE id_duakce=$id_akce");
    $msg= "Akce '$nazev' byla zrušena$msg2";
    goto end;
  }
  if ( $ret->ucastnici ) {
    // napřed zrušit účasti na akci
    $rs= query("DELETE FROM spolu USING spolu JOIN pobyt USING(id_pobyt) WHERE id_akce=$id_akce");
    $s= pdo_affected_rows($rs);
    $rs= query("DELETE FROM pobyt WHERE id_akce=$id_akce");
    $p= pdo_affected_rows($rs);
  }
  $rs= query("DELETE FROM akce WHERE id_duakce=$id_akce");
  $a= pdo_affected_rows($rs);
  $msg= $a
    ? "Akce '$nazev' byla smazána" . ( $p+$s ? ", včetně $p účastí $s účastníků" : '')
    : "CHYBA: akce '$nazev' nebyla smazána";
  $msg.= $msg2;
end:
  return $msg;
}
# ---------------------------------------------------------------------------------- akce2 zmeny_web
# vrácení položek daného pobytu u kterých došlo ke změně uživatelem WEB
function akce2_zmeny_web($idp) {  trace();
  // získání sledovaných klíčů tabulek spolu, osoba, tvori, rodina
  $n= 0;
  $keys= (object)array('osoba'=>array(),'tvori'=>array(),'spolu'=>array()); // table -> [id_table]
  $flds= (object)array();
  $idr= 0;
  $rp= pdo_qry("
    SELECT id_rodina,id_tvori,o.id_osoba,t.id_osoba,id_spolu
    FROM pobyt AS p
    JOIN akce ON id_akce=id_duakce
    JOIN spolu AS s USING (id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_rodina=i0_rodina
    WHERE id_pobyt=$idp
  ");
  while ( $rp && (list($_idr,$idt,$ido1,$ido2,$ids)= pdo_fetch_array($rp)) ) {
    if (!in_array($ido1,$keys->osoba)) $keys->osoba[]= $ido1;
    if ($ido2 && !in_array($ido2,$keys->osoba)) $keys->osoba[]= $ido2;
    if (!in_array($idt,$keys->tvori)) $keys->tvori[]= $idt;
    if (!in_array($ids,$keys->spolu)) $keys->spolu[]= $ids;
//    $keys->rodina= $idr;
    $idr= $_idr;
  }
  $idos= implode(',',$keys->osoba);
  $idts= implode(',',$keys->tvori);
  $idss= implode(',',$keys->spolu);
//                                                         debug($keys,'klíče');
  // projití _track - zjištění vzniku pobytu
  $start= select('kdy','_track',"kde='pobyt' AND klic='$idp' ORDER BY kdy LIMIT 1");
  // posbírání pozdějších změn 
  $n= 0;
  $rt= pdo_qry("SELECT kde,klic,fld,GROUP_CONCAT(kdo ORDER BY kdy DESC) FROM _track
      WHERE kdy>='$start' AND (
        (kde='pobyt' AND klic=$idp AND fld NOT IN ('id_akce','i0_rodina','web_zmena','web_changes','web_json') )"
       . ($idr  ? " OR (kde='rodina' AND klic=$idr AND fld NOT IN ('access','web_zmena') )" : '')
       . ($idos ? " OR (kde='osoba' AND klic IN ($idos) AND fld NOT IN ('access','web_zmena') )" : '')
       . ($idts ? " OR (kde='tvori' AND klic IN ($idts) AND fld NOT IN ('id_rodina','id_osoba') )" : '')
       . ($idss ? " OR (kde='spolu' AND klic IN ($idss) AND fld NOT IN ('id_pobyt','id_osoba') )" : '')
      .")
      GROUP BY kde,klic,fld  "
      );
  while ( $rt && (list($kde,$klic,$fld,$kdo)= pdo_fetch_array($rt)) ) {
    if (substr($kdo,0,3)!='WEB') continue; // barvíme jen změny z webu
    switch ( $kde ) {
    case 'pobyt':  $flds->pobyt[$klic][]= $fld; $n++; break;
    case 'rodina': $flds->rodina[$klic][]= $fld; $n++; break;
    case 'osoba':  $flds->osoba[$klic][]= $fld; $n++; break;
    case 'tvori':  $flds->tvori[$klic][]= $fld; $n++; break;
    case 'spolu':  $flds->spolu[$klic][]= $fld; $n++; break;
    }
  }
                                        debug($flds,"'položky - $n změn");
  return $flds;
}
# -------------------------------------------------------------------------------------- akce2 zmeny
# vrácení klíčů pobyt u kterých došlo ke změně po daném datu a čase
function akce2_zmeny($id_akce,$h) {  trace();
  $ret= (object)array('errs'=>'','pobyt'=>'','chngs'=>array(),'osoby'=>array());
  // přebrání parametrů
  $time= date_sub(date_create(), date_interval_create_from_date_string("$h hours"));
  $ret->kdy= date_format($time, 'Y-m-d H:i');
  // získání sledovaných klíčů tabulek spolu, osoba, tvori, rodina
  $pobyt= $osoba= $osoby= $rodina= $spolu= $spolu_osoba= $tvori= array();
  $rp= pdo_qry("
    SELECT id_pobyt,id_spolu,o.id_osoba,id_tvori,id_rodina
    FROM pobyt AS p
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_akce=$id_akce
  ");
  while ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $pid= $p->id_pobyt;
    $spolu[$p->id_spolu]= $pid;
    $spolu_osoba[$p->id_spolu]= $p->id_osoba;
    $osoba[$p->id_osoba]= $pid;
    if ( $p->id_tvori ) $tvori[$p->id_tvori]= $pid;
    if ( $p->id_rodina ) $rodina[$p->id_rodina]= $pid;
  }
//                                                         debug($rodina);
  // projití _track
  $n= 0;
  $rt= pdo_qry("SELECT kde,klic,fld,kdo,kdy FROM _track WHERE kdy>'{$ret->kdy}'");
  while ( $rt && ($t= pdo_fetch_object($rt)) ) {
    $k= $t->klic;
    $pid= 0;
    switch ( $t->kde ) {
    case 'pobyt':  $pid= $k; break;
    case 'spolu':  if ( ($pid= $spolu[$k]) ) $osoby[$spolu_osoba[$k]]= 1; break;
    case 'osoba':  if ( ($pid= $osoba[$k]) ) $osoby[$k]= 1; break;
    case 'tvori':  $pid= $tvori[$k]; break;
    case 'rodina': $pid= $rodina[$k]; break;
    }
    if ( $pid ) {
      if ( !in_array($pid,$pobyt) )
        $pobyt[]= $pid;
      // vygenerování změnového objektu pro obarvení změn [[table,key,field],...]
      $kdy= sql_time1($t->kdy);
      $ret->chngs[]= array($t->kde,$k,$t->fld,$t->kdo,$kdy);
      $n++;
    }
  }
  // shrnutí změn
  $ret->osoby= implode(',',array_keys($osoby));
  $ret->pobyt= implode(',',$pobyt);
//                                        debug($ret,"$n změn po ... sql_time={$ret->kdy}");
  return $ret;
}
# --------------------------------------------------------------------------------- xx akce_dite_kat
# vrátí ys_akce_dite_kat nebo fa_akce_dite_kat podle akce
function xx_akce_dite_kat($id_akce) {  //trace();
  $org= select1("access","akce","id_duakce=$id_akce");
  return 
      $org==1 ? 'ys_akce_dite_kat' : (
      $org==2 ? 'fa_akce_dite_kat' : (
      $org>=4 ? 'ms_akce_dite_kat' : ''));
}
function akce_test_dite_kat($kat,$narozeni,$id_akce) {  trace();
  $ret= (object)array('ok'=>0,'vek'=>0.0);
  $dite_kat= xx_akce_dite_kat($id_akce);
  $od_do= select1("ikona","_cis","druh='$dite_kat' AND data=$kat"); // věk od-do
  list($od,$do)= explode('-',$od_do);
  $akce_od= select1("datum_od","akce","id_duakce=$id_akce");
  $narozeni= sql_date1($narozeni,1);
  $date1 = new DateTime($narozeni);
  $date2 = new DateTime($akce_od);
  $diff= $date2->diff($date1,1);
  $x= $diff->y . " years, " . $diff->m." months, ".$diff->d." days ";
  $roku= $diff->y;
  $vek= $diff->y+($diff->m+$diff->d/30)/12;
//   $d= array($diff->y,$diff->m,$diff->d,$diff->days);
//                                               debug($d,"$vek: $x, narozen:$narozeni, akce:$akce_od");
  $ret->vek= round($vek,1);
  $ret->ok= $vek>=$od && $vek<$do ? 1 : 0;
  return $ret;
}
# ------------------------------------------------------------------------------- akce2 strava_denne
# vrácení výjimek z providelné stravy jako pole
function akce2_strava_denne($od,$dnu,$cela,$polo) {  #trace('');
  $dny= array('neděle','pondělí','úterý','středa','čtvrtek','pátek','sobota');
  $strava= array();
  $den0= sql2stamp($od);
  for ($i= 0; $i<3*$dnu; $i+= 3) {
    $t= $den0+($i/3)*60*60*24;
    $den= date('d.m.Y ',$t);
    $den.= $dny[date('w',$t)];
    $strava[]= (object)array(
      'den'=> $den,
      'sc' => substr($cela,$i+0,1),
      'sp' => substr($polo,$i+0,1),
      'oc' => substr($cela,$i+1,1),
      'op' => substr($polo,$i+1,1),
      'vc' => substr($cela,$i+2,1),
      'vp' => substr($polo,$i+2,1)
    );
  }
//                                                 debug($strava,"akce_strava_denne($od,$dnu,$cela,$polo) $den0");
  return $strava;
}
# -------------------------------------------------------------------------- akce2 strava_denne_save
# zapsání výjimek z pravidelné stravy - pokud není výjimka zapíše prázdný string
#   $x= ''|'_bm'|'bl'  - kód typu diety
#   $prvni - kód první stravy na akci
function akce2_strava_denne_save($id_pobyt,$dnu,$x,
    $cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str,$prvni) {  trace('');
  $cela_ruzna= $polo_ruzna= 0;
  $i0= $prvni=='s' ? 0 : ($prvni=='o' ? 1 : ($prvni=='v' ? 2 : 2));
  // zjístíme, zda je vůbec nějaká výjimka
  for ($i= $i0; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( ";$cela"!=";$cela_str" ) $set.= "cstrava_cel$x='$cela'";     // ; jako ochrana pro pochopení jako čísla
  if ( ";$polo"!=";$polo_str" ) $set.= ($set?',':'')."cstrava_pol$x='$polo'";
  if ( $set ) {
    $qry= "UPDATE pobyt SET $set WHERE id_pobyt=$id_pobyt";
    $res= pdo_qry($qry);
  }
//                                                 display("akce_strava_denne_save(($id_pobyt,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ========================================================================================> . hnízda
# -------------------------------------------------------------------------------- akce2 hnizda_test
# seznam hnízd musí začínat různým písmenem
function akce2_hnizda_test($hnizda_seznam) {  
  $msg= '';
  $hnizda= preg_split("/\s*,\s*/",$hnizda_seznam);
  if ( !$hnizda_seznam || count($hnizda)<2 ) {
    $msg= "zadejte jména hnízd jako seznam oddělený čárkami"; goto end;
  }
  $h= array();
  foreach($hnizda as $hnizdo) {
    $h0= strtoupper($hnizdo[0]);
    if ( !strlen($h0) ) {
      $msg= "v seznamu hnízd je čárka navíc"; goto end;
    }
    if ( in_array($h0,$h) ) {
      $msg= "hnízda musí mít různá první písmena"; goto end;
    }
    $h[]= $h0;
  }
end:  
  return $msg;
}
# ----------------------------------------------------------------------------- akce2 hnizda_options
# vrátí definici mapy: hnizdo=klíč,zkratka,nazev
# spoléhá na korektní definici
# pokud x=1 přidá všichni+nezařazení, pokud x=0 bude 0-nezařazení, pokud x=2 bude 0-všichni
function akce2_hnizda_options($hnizda_seznam,$x=0) {  
  if (!$hnizda_seznam) return '';
  $hnizda= preg_split("/\s*,\s*/",$hnizda_seznam);
  $text= $x==1 ? "99::všichni,0:-:nezařazení" : ($x==0 ? '0:?:nezařazení' : '0::všichni');
  foreach($hnizda as $i=>$hnizdo) {
    $h0= strtoupper($hnizdo[0]);
    $i1= $i+1;
    $text.= ",$i1:$h0:$hnizdo";
  }
  return $text;
}
# ======================================================================================> . intranet
//g# -------------------------------------------------------------------------------- akce2 roku_update
//g# přečtení listu $rok (>2010) z tabulky ciselnik_akci a zapsání dat do tabulky
//g# načítají se jen řádky ve kterých typ='a'
//g# funguje pro "nové tabulky google" - od dubna 2015
//gfunction akce2_roku_update($rok) {  trace();
//g//  global $json;
//g//  $key= "1RKnvU7EJG7YtBDjnSpQfwg3kjOCBEV_w8bMlJcdV8Nc";         // neplatné  
//g  $key=   "16nu8StIpIPD9uY-cskQOAXCnVY6hL9OTaTBdpPoUrnA";         // ciselnik_akci
//g  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
//g  $sheet= $rok>2010 ? $rok-1997 : ($rok==2010 ? 10 : -1);
//g  // https://docs.google.com/spreadsheets/d/1RKnvU7EJG7YtBDjnSpQfwg3kjOCBEV_w8bMlJcdV8Nc/gviz/tq?tqx=out:json&sheet=
//g  $ciselnik= "https://docs.google.com/spreadsheets/d/$key/gviz/tq?tqx=out:json&sheet=$sheet";
//g  $x= file_get_contents($ciselnik);
//g                                        display("$ciselnik=$x");
//g  $xi= strpos($x,$prefix);
//g  $xl= strlen($prefix);
//g//                                         display("xi=$xi,$xl");
//g  $x= substr(substr($x,$xi+$xl),0,-2);
//g//                                         display($x);
//g  $tab= json_decode($x)->table;
//g//                                         debug($tab,$sheet);
//g  // projdeme získaná data
//g  $n= 0;
//g  if ( $tab ) {
//g    // zrušení daného roku v GAKCE
//g    $qry= "DELETE FROM g_akce WHERE g_rok=$rok";
//g    $res= pdo_qry($qry);
//g    // výběr a-záznamů a zápis do G_AKCE
//g    $values= ''; $del= '';
//g    foreach ($tab->rows as $crow) {
//g      $row= $crow->c;
//g      $kat= $row[0]->v;
//g      if ( strpos(' a',$kat) ) {
//g//                                                         debug($row,$row[2]->v);
//g        $n++;
//g        $kod= isset($row[1]->f) ? $row[1]->f : $row[1]->v;
//g        $id= 1000*$rok+$kod;
//g        $nazev= pdo_real_escape_string($row[2]->v);
//g//                                                        display("$kod:$nazev");
//g        // data akce - jen je-li syntax ok
//g        $od= $do= '';
//g        $x= isset($row[3]->f) ? $row[3]->f : $row[3]->v;
//g        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
//g          $od= sql_date($x,1);
//g        $x= isset($row[4]->f) ? $row[4]->f : $row[4]->v;
//g        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
//g          $do= sql_date($x,1);
//g        // kontrola roku
//g        if ( !(($od && substr($od,0,4)==$rok) || ($do && substr($do,0,4)==$rok)) ) {
//g                                                    display("od=$od do=$do rok=$rok");
//g          fce_warning("akce '$nazev' není z daného roku "
//g              . "NEBO obnovovaný rok není na intranetu jako první sešit");
//g          $n= 0;
//g          goto end;
//g        }
//g        $uc=  isset($row[5]->f) ? $row[5]->f : $row[5]->v;
//g        $typ= isset($row[6]->f) ? $row[6]->f : $row[6]->v;
//g        $kap= isset($row[7]->f) ? $row[7]->f : $row[7]->v;
//g        $values.= "$del($id,$rok,'$kod',\"$nazev\",'$od','$do','$uc','$typ','$kap','$kat')";
//g        $del= ',';
//g      }
//g    }
//g    $qry= "INSERT INTO g_akce (id_gakce,g_rok,g_kod,g_nazev,g_od,g_do,g_ucast,g_typ,g_kap,g_kat)
//g           VALUES $values";
//g    $res= pdo_qry($qry);
//g  }
//g  // konec
//g end:
//g  return $n;
//g}
# ==================================================================================> . mrop firming
# ------------------------------------------------------------------------------- akce2 confirm_mrop
# zjištění, zda lze účastníků akce zapsat běžný rok jako datum iniciace
# zapsání roku iniciace účastníkům akce (write=1)
function akce2_confirm_mrop($ida,$write=0) {  trace();
  $ret= (object)array('ok'=>0,'msg'=>'ERROR');
  $letos= date('Y');
  if ( !$write ) {
    // jen sestavení confirm
    $ra= pdo_qry("
      SELECT nazev, mrop, YEAR(datum_od) AS _rok,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce=0 AND iniciace IN (0,$letos))) as _mladi,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce=0 AND iniciace NOT IN (0,$letos))) as _starsi
      FROM akce AS a
      LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce
      WHERE id_duakce=$ida
      GROUP BY id_duakce
    ");
    if ( !$ra ) goto end;
    list($nazev,$mrop,$rok,$mladi,$starsi)= pdo_fetch_array($ra);
    $ret->ok= $mrop && $rok==$letos && !$starsi && $mladi;
    $ret->msg= $ret->ok
      ? "Opravdu mám pro $mladi účastníků akce <b>\"$nazev/$rok\"</b>
        potvrdit rok $letos jako rok jejich iniciace (MROP)?"
      : "CHYBA";
  }
  else {
    // zápis roku iniciace, včetně záznamu do _track
    global $USER;
    $user= $USER->abbr;
    $now= date("Y-m-d H:i:s");
    $n= $n1= $n2= 0;
    $ra= pdo_qry("
      SELECT COUNT(*),GROUP_CONCAT(id_osoba)
      FROM pobyt AS p
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_akce=$ida AND funkce=0 AND iniciace IN (0)
      GROUP BY id_akce
    ");
    if ( !$ra ) goto end;
    list($n,$ids)= pdo_fetch_array($ra);
    if ( $n ) {
      $rs= query("UPDATE osoba SET iniciace=$letos WHERE id_osoba IN ($ids)");
      $n1= pdo_affected_rows($rs);
      // zápis do _track
      foreach ( explode(',',$ids) as $ido) {
        $rs= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
               VALUES ('$now','$user','osoba',$ido,'iniciace','u','0','$letos')");
        $n2+= pdo_affected_rows($rs);
      }
    }
    $ret->ok= $n>0 && $n==$n1 && $n==$n2;
    $ret->msg= $ret->ok
      ? "$n účastníkům byl zapsán rok $letos jako rok jejich iniciace"
      : "ERROR ($n,$n1,$n2)";
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------- akce2 confirm_firm
# zjištění, zda lze účastníků akce zapsat datum posledního firmingu
# zapsání roku posledního firmingu účastníkům akce (write=1) + hosdpodáři + lektoři
function akce2_confirm_firm($ida,$write=0) {  trace();
  $ret= (object)array('ok'=>0,'msg'=>'ERROR');
  if ( !$write ) {                                                            
    // jen sestavení confirm
    $ra= pdo_qry("
      SELECT nazev, firm, YEAR(datum_od) AS _rok,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce IN (0,5,12))) as _sloni
      FROM akce AS a
      LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce
      WHERE id_duakce=$ida
      GROUP BY id_duakce
    ");
    if ( !$ra ) goto end;
    list($nazev,$firm,$rok,$sloni)= pdo_fetch_array($ra);
    $ret->ok= $firm && $sloni;
    $ret->msg= $ret->ok
      ? "Opravdu mám pro $sloni účastníků akce <b>\"$nazev/$rok\"</b>
        zapsat rok $rok jako účast na firmingu?"
      : "CHYBA";
  }
  else {
    // zápis roku firmingu, včetně záznamu do _track
    global $USER;
    $user= $USER->abbr;
    $now= date("Y-m-d H:i:s");
    $n= $n1= $n2= 0;
    $ra= pdo_qry("
      SELECT COUNT(*),GROUP_CONCAT(id_osoba), YEAR(a.datum_od) AS _rok
      FROM pobyt AS p
      JOIN akce AS a ON p.id_akce=a.id_duakce
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_akce=$ida AND funkce IN (0,5,12) AND firming!=YEAR(a.datum_od)
      GROUP BY id_akce
    ");
    if ( !$ra ) goto end;
    list($n,$ids,$rok)= pdo_fetch_array($ra);
    if ( $n ) {
      $rs= query("UPDATE osoba SET firming=$rok WHERE id_osoba IN ($ids)");
      $n1= pdo_affected_rows($rs);
      // zápis do _track
      foreach ( explode(',',$ids) as $ido) {
        $rs= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
               VALUES ('$now','$user','osoba',$ido,'firming','u','0','$rok')");
        $n2+= pdo_affected_rows($rs);
      }
    }
    else 
      $n= 0;
//    $ret->ok= $n>0 && $n==$n1 && $n==$n2;
    $ret->msg= "$n účastníkům byl doplněn rok $rok jako rok účasti na firmingu";
//      : "ERROR ($n,$n1,$n2)";
  }
end:
  return $ret;
}
# ====================================================================================> . ceník akce
# ------------------------------------------------------------------------------- akce2 select_cenik
# seznam akcí s ceníkem pro select
function akce2_select_cenik($id_akce) {  trace();
  $max_nazev= 30;
  mb_internal_encoding('UTF-8');
  $org= select('access','akce',"id_duakce=$id_akce");
  $options= 'neměnit:0';
  if ( $id_akce ) {
    $qa= "SELECT id_duakce, nazev, YEAR(datum_od) AS _rok FROM akce
          WHERE id_duakce!=$id_akce AND access=$org AND ma_cenik>0 ORDER BY datum_od DESC";
    $ra= pdo_qry($qa);
    while ($ra && $a= pdo_fetch_object($ra) ) {
      $nazev= "{$a->_rok} - ";
      $nazev.= strtr($a->nazev,array(','=>' ',':'=>' '));
      if ( mb_strlen($nazev) >= $max_nazev )
        $nazev= mb_substr($nazev,0,$max_nazev-3).'...';
      $options.= ",$nazev:{$a->id_duakce}";
    }
  }
  return $options;
}
# ------------------------------------------------------------------------------- akce2 change_cenik
# změnit ceník akce za vybraný
# fáze= dotaz | proved
# 1. kontrola parametrů 
function akce2_change_cenik($faze,$id_akce,$id_akce_vzor) {  trace();
  if ( !$id_akce || !$id_akce_vzor ) { $msg= "nebyl vybrán zdroj ceníku!"; goto end; }
  $hnizda= select('hnizda','akce',"id_duakce=$id_akce_vzor");
  if ( $hnizda ) { $msg= "kopírovat ceník z hnízd do hnízd zatím neumím!"; goto end; }
  $hnizda= select('hnizda','akce',"id_duakce=$id_akce");
  if ($faze=='dotaz') {
    $msg= $hnizda 
        ? "opravdu nastavit ceníky ve všech hnízdech akce jako stejné podle ceníku vybrané akce?" 
        : "opravdu nastavit ceník akce za cením vybrané akce?";
    goto end;
  }
  // 'proved' kopii, napřed vymaž starý ceník
  query("DELETE FROM cenik WHERE id_akce=$id_akce");
  // kopie ze vzoru
  if ($hnizda) { // do hnízd
    $n_h= count(explode(',',$hnizda));
    for ($h=1; $h<=$n_h; $h++) {
      query("INSERT INTO cenik (id_akce,hnizdo,poradi,polozka,za,typ,od,do,cena,dph)
          SELECT $id_akce,$h,poradi,polozka,za,typ,od,do,cena,dph
          FROM cenik WHERE id_akce=$id_akce_vzor");
    }
  }
  else { // bez hnízd
    query("INSERT INTO cenik (id_akce,poradi,polozka,za,typ,od,do,cena,dph)
          SELECT $id_akce,poradi,polozka,za,typ,od,do,cena,dph
          FROM cenik WHERE id_akce=$id_akce_vzor");
  }
  $msg= "hotovo, nezapomeňte jej upravit (ceny,DPH)";
end:
  return $msg;
}
# --------------------------------------------------------------------------------- akce2 platby_xls
function akce2_platby_xls($id_akce) {  trace();
  $ret= (object)array('ok'=>0,'xhref'=>'','msg'=>'');
  $test= 0;
  $name= "cenik_$id_akce";
  list($nazev,$rok)= select("nazev,YEAR(datum_do)","akce","id_duakce=$id_akce");
  // vytvoření sešitu s ceníkem
  $n= 1;
  $xls= <<<__XLS
  |open  $name|\n\n|sheet cenik|columns A=40
    |A$n Ceník pro akci $nazev $rok::bold size=16\n
__XLS;
  // výpis ceníku podle typu ubytování
  $tit= "|B* cena|C* DPH|A*:C* bcolor=ffcccccc bold\n";
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= pdo_qry($qa);
  if ( !$ra || !pdo_num_rows($ra) ) {
    $ret->msg.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    goto end;
  }
  while ( $ra && ($a= pdo_fetch_object($ra)) ) {
    $pol= $a->polozka;
    $bc= '';
    if ( substr($pol,0,2)=='--' ) {
      $n+= 2;
      $xls.= "|A$n ".substr($pol,2).str_replace('*',$n,$tit);
    }
    elseif ( substr($pol,0,1)=='-' ) {
      $n+= 3;
      $xls.= "|A$n ".substr($pol,1)."|A$n:C$n bcolor=ffaaaaff bold size=14\n";
    }
    else {
      $n++;
      $za= $a->za;
      $cena= $a->cena;
      $dph= $a->dph;
      $xls.= "|A$n $pol ($za)|B$n $cena|C$n $dph\n";
    };
  }
  // konec
  $xls.= "|close|";
  $ret->msg= Excel5($xls,!$test);
  if ( $ret->msg ) goto end;
  $ret->ok= 1;
  $ret->xhref= " zde lze stáhnout <a href='docs/$name.xls' target='xls'>$name.xls</a>.";
//                                                         if ($test) display(($xls));
end:
//                                                         debug($ret);
  return $ret;
}
// # ------------------------------------------------------------------------------ akce2 pobyt_def2017
// # výpočet parametrů pro výpočet defaultní ceny výlučně podle ceníku - podle sloupců
// #   pro: U=účastník, S=~VPS, D=dítě, d=dítě s chůvou, C=chůva, p=pom.peč., P=pečovatel, H=host
// #    za: n=noc, s=snídaně, o=oběd, v=večeře, p=pobyt, a=aktivita
// # od-do: věk (od je včetně, do nevčetně narozenin) obě nulové znamenají, že na věku nesejde
// #
// function akce2_pobyt_def2017($id_pobyt,$zapsat=0) {  trace();
//   // předávané parametry
//   $luzka= $pristylky= $noci= $kocarky= $cela= $detska= $chuvy= $vzorec= 0;
//   // projítí účastníků pobytu (tabulka spolu)
//   $msg= '';
//   $qo= "SELECT o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce,
//          s.pecovane,s.s_role,s.dite_kat,(SELECT CONCAT(osoba.id_osoba,',',pobyt.id_pobyt)
//           FROM pobyt
//           JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
//           JOIN osoba ON osoba.id_osoba=spolu.id_osoba
//           WHERE pobyt.id_akce=a.id_duakce AND spolu.pecovane=o.id_osoba) AS _chuva
//         FROM spolu AS s JOIN osoba AS o USING(id_osoba) JOIN pobyt AS p USING(id_pobyt)
//         JOIN akce AS a ON p.id_akce=a.id_duakce
//         WHERE id_pobyt=$id_pobyt";
//   $ro= pdo_qry($qo);
//   while ( $ro && ($o= pdo_fetch_object($ro)) ) {
//     // informace z akce, pobyt
//     $noci= $o->_noci;
//     $fce= $o->funkce;
//     // informace ze spolu
//     if ( $o->_chuva ) {
//       $dosp++; $luzka++; $cela++; $chuva++;     // platíme za chůvu svého dítěte,
//     }
//     if ( $o->pecovane) {                        // za dítě-chůvu platí rodič pečovaného dítěte
//       $dosp--; $luzka--; $cela--;
//     }
//     $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
//     $msg.= " {$o->jmeno}:$vek";
//     if ( in_array($o->s_role,array(2,3,4)) && $o->dite_kat ) {
//       // pokud je definována kategorie podle _cis/ms_akce_dite_kat ALE dítě není pečoun
//       $dite++;
//       list($spani,$strava)= explode(',',$ms_akce_dite_kat[$o->dite_kat]);
//       // lůžka
//       if ( $spani=='L' )      $luzka++;
//       elseif ( $spani=='-' )  $bez++;
//       else $err+= "chybná kategorie dítěte";
//       // strava
//       if ( $strava=='c' )     $cela++;
//       elseif ( $strava=='p' ) $polo++;
//       else $err+= "chybná kategorie dítěte";
//     }
//     else {
//       // jinak se orientujeme podle věkových hranic: 0-3-10-18
//       if     ( $vek<3  ) { $koje++;  $bez++; }                  // dítě bez lůžka a stravy
//       elseif ( $vek<10 ) { $deti++;  $luzka++; $polo++; }       // dítě lůžko poloviční
//       elseif ( $vek<18 ) { $deti++;  $luzka++; $cela++; }       // dítě lůžko celá
//       else               { $dosp++;  $luzka++; $cela++; }       // dospělý lůžko celá
//     }
//   }
//   // zápis do pobytu
//   if ( $zapsat ) {
// //     query("UPDATE pobyt SET luzka=".($dosp+$deti).",kocarek=$koje,strava_cel=$dosp,strava_pol=$deti,
// //              pocetdnu=$noci,svp=$svp WHERE id_pobyt=$id_pobyt");
//     query("UPDATE pobyt SET luzka=$luzka,kocarek=$bez,strava_cel=$cela,strava_pol=$polo,
//              pocetdnu=$noci,svp=$svp WHERE id_pobyt=$id_pobyt");
//   }
//   //$ret= (object)array('luzka'=>$dosp+$deti,'kocarek'=>$koje,'pocetdnu'=>$noci,'svp'=>$svp,
//   //                    'strava_cel'=>$dosp,'strava_pol'=>$deti,'vzorec'=>$fce);
//   $ret= (object)array('luzka'=>$luzka,'kocarek'=>$bez,'pocetdnu'=>$noci,'svp'=>$svp,
//                       'strava_cel'=>$cela,'strava_pol'=>$polo,'vzorec'=>$fce,'vek'=>$vek);
//                                                 debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
//   return $ret;
// }
# ------------------------------------------------------------------------- akce2 pobyt_default_vsem
# provedení akce2_pobyt_default pro všechny
function akce2_pobyt_default_vsem($id_akce) {  trace();
  $warn= $zmeny= '';
  $a= akce2_id2a($id_akce);
  $ro= pdo_qry("
    SELECT id_pobyt,prijmeni,
      CONCAT(cstrava_cel,cstrava_cel_bm,cstrava_cel_bl,
             cstrava_pol,cstrava_pol_bm,cstrava_pol_bl) AS _c
    FROM pobyt 
    JOIN spolu USING (id_pobyt)
    JOIN osoba USING (id_osoba)
    WHERE id_akce=$id_akce AND funkce!=99 
    -- AND id_pobyt IN (54153)
    GROUP BY id_pobyt
  ");
  while ( $ro && (list($id_pobyt,$prijmeni,$spec_strava)= pdo_fetch_row($ro)) ) {
    // test prázdnosti speciálních strav tj. cstrava_cel*,cstrava_pol*
    if ( $spec_strava ) {
      $warn.= " $prijmeni má nastavenu speciální stravu ";
    }
    $x= akce2_pobyt_default($id_akce,$id_pobyt,1,1);
    $warn.= $x->warn;
    // pokud nebylo varování - zápis do pobytu 
    if ( !$x->warn ) {
      query("UPDATE pobyt SET luzka=$x->luzka,kocarek=$x->kocarek,strava_cel=$x->strava_cel,
        strava_pol=$x->strava_pol,pocetdnu=$x->pocetdnu,svp=$x->svp,vzorec=$x->vzorec 
        WHERE id_pobyt=$id_pobyt");
      // aplikace vzorce
      if ( $a->soubeh ) {
        $c= akce2_vzorec_soubeh($id_pobyt,$id_akce,$a->soubezna); 
        if ( $c->err ) $warn.= " {$c->err} (pobyt $id_pobyt)";
      }
      else {
        $c= akce2_vzorec($id_pobyt);
//                                              debug($c,"akce2_vzorec($id_pobyt)");
      }
      if ( !isset($c->err) || !$c->err ) {
        // zápis ceny
        query("UPDATE pobyt SET 
          platba1='{$c->c_nocleh}',platba2='{$c->c_strava}',platba3='{$c->c_program}',
          platba4='{$c->c_sleva}',poplatek_d='{$c->poplatek_d}',naklad_d='{$c->naklad_d}'
          WHERE id_pobyt=$id_pobyt");
      }
      // informace o změnách kategorie dětí do warn
      if ( $x->zmeny_kat) 
        $zmeny.= "<br>$x->zmeny_kat";
    }
  }
  $info= $warn 
      ? "$warn<hr>výše uvedeným nebyly platby předepsány" 
      : 'byly předepsány všechny platby' ;
  $info.= $zmeny
      ? "<hr>následujícím dětem byla změněny kategorie $zmeny"
      : " nebyla změněna kategorie žádnému dítěti";
  return $info;
}
# ------------------------------------------------------------------------------ akce2 pobyt_default
# definice položek v POBYT podle počtu a věku účastníků - viz akce_vzorec_soubeh
# 150216 při vyplnění dite_kat budou stravy počítány podle _cis/ms_akce_dite_kat.barva
# 130522 údaje za chůvu budou připsány na rodinu chovaného dítěte
# 130524 oživena položka SVP
# 190501 pokud je $zapsat=1 budou dětem stanoveny kategorie podle věku
# 210510 hnízda
function akce2_pobyt_default($id_akce,$id_pobyt,$zapsat=0) {  //trace();
  $warn= '';
  $zmeny_kat= array(); // pro zapsat==1 bude obsahovat provedené změny kategorie u dětí
  $dite_kat= xx_akce_dite_kat($id_akce);
  $akce_dite_kat_Lp=  map_cis($dite_kat,'barva'); // {L|P|-},{c|p} = lůžko/pristylka/bez, celá/poloviční
  $akce_dite_kat_vek= map_cis($dite_kat,'ikona'); // od-do
  $akce_dite_kat_zkr= map_cis($dite_kat,'zkratka'); // zkratka
  $akce_funkce= map_cis('ms_akce_funkce','zkratka');
  // projítí společníků v pobytu
  $dosp= $deti= $koje= $noci= $sleva= $fce= $svp= 0;
  $luzka= $pristylky= $bez= $cela= $polo= 0;
  $msg= '';
  $qo= "SELECT o.prijmeni,o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce,
         s.pecovane,s.s_role,s.dite_kat,id_spolu,
         (SELECT CONCAT(osoba.id_osoba,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=a.id_duakce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s JOIN osoba AS o USING(id_osoba) JOIN pobyt AS p USING(id_pobyt)
        JOIN akce AS a ON p.id_akce=a.id_duakce
        WHERE id_pobyt=$id_pobyt";
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    if ( $o->_chuva ) {
      $dosp++; $luzka++; $cela++;       // platíme za chůvu vlastního dítěte
      $svp++;                           // ale ne za obecného pečouna
    }
    if ( $o->pecovane) {                // za dítě-chůvu platí rodič pečovaného dítěte
      $dosp--; $luzka--; $cela--;
    }
    $noci= $o->_noci;
    $fce= $o->funkce;
    $jmeno= "<i>{$o->prijmeni} {$o->jmeno}</i>";
    $_fce= $akce_funkce[$fce];
    if ( $_fce=='-' ) $_fce= 'účastník';
    $vek0= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
    $vek= roku_k($o->narozeni,$o->datum_od);
//                                        display("$_fce $jmeno vek=$vek ($vek0)");
    $msg.= " {$o->jmeno}:$vek";
    // s-role: 2,3,4=dítě, s peč. ,pom.peč. - v tom případě je otevřena volba dite-kat
    if ( in_array($o->s_role,array(2,3,4)) ) {
      $ktg= $o->dite_kat;
      // pokud prepsat=1 => kategorie dítěte bude stanovena podle věku
      // a informace o změně bude zapsána do zmeny_kat[]
      if ( $zapsat ) {
        $ok= 0;
        foreach ($akce_dite_kat_vek as $kat=>$veky) {
          list($od,$do)= explode('-',$veky);
          if ( $vek>=$od && $vek<$do) {
            if ($kat!=$ktg) {
              $zmeny_kat[]= array($o->id_spolu,"$o->prijmeni $o->jmeno",$ktg,$kat);
              query("UPDATE spolu SET dite_kat=$kat WHERE id_spolu={$o->id_spolu} ");
            }
            $ok= 1;
            break;
          }
        }
        if ( !$ok ) 
          $warn.= " $_fce $jmeno nemá dětský věk, ";
      }
      if ( $ktg ) {
        // pokud je definována kategorie podle _cis/akce_dite_kat ALE dítě není pečoun
        $deti++;
        list($spani,$strava)= explode(',',$akce_dite_kat_Lp[$ktg]);
        // lůžka
        if ( $spani=='L' )      $luzka++;
        elseif ( $spani=='P' )  $pristylky++;
        elseif ( $spani=='-' )  $bez++;
        else $err+= "chybná kategorie dítěte";
        // strava
        if ( $strava=='c' )     $cela++;
        elseif ( $strava=='p' ) $polo++;
        else $err+= "chybná kategorie dítěte";
      }
      else {
        $warn.= "$_fce $jmeno nemá nastavenou kategorii, ";
      }
    }
    else {
      if ( $vek>18 || in_array($o->s_role,array(0,5)) ) {
        $dosp++;  $luzka++; $cela++; // dospělý lůžko celá
      }
      else {
        $warn.= " $_fce $jmeno nemá 18 let, ";
      }
//      // jinak se orientujeme podle věkových hranic: 0-3-10-18
//      if     ( $vek<3  ) { $koje++;  $bez++; }                  // dítě bez lůžka a stravy
//      elseif ( $vek<10 ) { $deti++;  $luzka++; $polo++; }       // dítě lůžko poloviční
//      elseif ( $vek<18 ) { $deti++;  $luzka++; $cela++; }       // dítě lůžko celá
//      else               { $dosp++;  $luzka++; $cela++; }       // dospělý lůžko celá
    }
  }
  // určení vzorce
  $vzorec= 
      in_array($fce,array(1,2)) ?   1 : (
      in_array($fce,array(5)) ?     2 : (
      in_array($fce,array(3,4,6)) ? 3 : 0));      
  // vrácení hodnot
  $ret= (object)array('luzka'=>$luzka,'pristylky'=>$pristylky,'kocarek'=>$bez,'pocetdnu'=>$noci,'svp'=>$svp,
                      'strava_cel'=>$cela,'strava_pol'=>$polo,'vzorec'=>$vzorec,'vek'=>$vek,
                      'warn'=>$warn);
  if ($zapsat) { 
//                        if (count($zmeny_kat)) debug($zmeny_kat,"změny kategorie dětí");
    $zmeny= $del= '';
    foreach ($zmeny_kat as $z) {
      // z~array($o->id_spolu,"o.prijmeni o.jmeno",$ktg,$kat);
      $zmeny.= "$del$z[1] - místo {$akce_dite_kat_zkr[$z[2]]} je {$akce_dite_kat_zkr[$z[3]]}";
      $del= ', ';
    }
    $ret->zmeny_kat= $zmeny;    
  }
//                                                debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 vzorec_expr
# test výpočtu platby za pobyt na akci pro ceník verze 2017
# $expr = {n}*{n2}..*pro.za + ...  kde N je písmeno znamenající počet nocí, O počet obědů
function akce2_vzorec_expr($id_akce,$hnizdo,$expr) {  trace();
  $expr= str_replace(' ','',$expr);
  $html= '';
  // akce
  list($ma_cenik,$noci,$strava_oddo,$hnizda)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo,hnizda","akce","id_duakce=$id_akce");
  $obedu= $noci + ($strava_oddo=='oo' ? 1 : 0);
  if ( !$ma_cenik ) { $html= 'akce nemá ceník'; goto end; }
  // ceník
  $AND_hnizdo= $hnizda ? "AND hnizdo=$hnizdo" : '';
  $cenik= array();
  $ra= pdo_qry("SELECT cena,pro,za FROM cenik WHERE id_akce=$id_akce AND za!='' $AND_hnizdo");
  while ( $ra && (list($cena,$pro,$za)= pdo_fetch_row($ra)) ) {
    foreach (str_split($pro) as $prox) {
      $cenik[$prox.$za]= $cena;
    }
  }
//                                                 debug($cenik);
  // výpočet
  $cena= 0;
  $terms= preg_split("/([+-])/m",$expr,-1,PREG_SPLIT_DELIM_CAPTURE);
  $count= count($terms);
  for ($j= 0; $j<$count; $j= $j+2 ) {
    $term= $terms[$j];
    $sign= $j ? $terms[$j-1] : '+';
    $n= explode('*',$term);
    $last= count($n)-1;
    list($pro,$vek)= explode('/',$pro);
    if ( !isset($cenik[$n[$last]]) ) {
      $html= "cena pro {$n[$last]} není v ceníku definovaná"; goto end;
    }
    $n[$last]= $cenik[$n[$last]];
    $ns= 1;
    for ($i= 0; $i<=$last; $i++) {
      $x=           $n[$i]=='N'
        ? $noci : ( $n[$i]=='O'
        ? $obedu
        : $n[$i]);
      $ns*= $x;
    }
    $cena+= $sign=='-' ? -$ns : $ns;
                                                display(" $sign $term=$ns ... $cena ");
  }
//                                                 debug($terms,$count);
  $html= "$expr = <b>$cena,-</b> <br><br><i>(N=$noci je počet nocí a menu, O=$obedu je počet obědů)</i>";
end:
  return $html;
}
# --------------------------------------------------------------------------- akce2 vzorec_expr_2017
# test výpočtu platby za pobyt na akci pro ceník verze 2017
# $expr = {n}*{n2}..*pro.za + ...
#   kde věk je věk, N je písmeno znamenající počet nocí, O počet obědů
function akce2_vzorec_expr_2017($id_akce,$expr,$vek) {  trace();
  $expr= str_replace(' ','',$expr);
  $html= $err= '';
  // akce
  list($ma_cenik,$noci,$strava_oddo)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
  $obedu= $noci + ($strava_oddo=='oo' ? 1 : 0);
  if ( !$ma_cenik ) { $err= 'akce nemá ceník'; goto end; }
  // výpočet
  $cena= 0;
  $terms= preg_split("/([+-])/m",$expr,-1,PREG_SPLIT_DELIM_CAPTURE);
  $count= count($terms);
  for ($j= 0; $j<$count; $j= $j+2 ) {
    // rozdělení na sčítance
    $term= $terms[$j];
    $sign= $j ? $terms[$j-1] : '+';
    // rozdělení na součinitele
    $n= explode('*',$term);
    $last= count($n)-1;
    $proza= $n[$last];
    // rozbor posledního pro.za.vek
    list($pro,$za)= str_split($proza);
    // zjištění ceny z ceníku
    $AND= isset($vek) ? "AND IF(od,$vek>=od,1) AND IF(do,$vek<do,1)" : '';
    $res= pdo_qry("
      SELECT cena,GROUP_CONCAT(poradi),COUNT(*)
      FROM cenik WHERE id_akce=$id_akce AND pro LIKE BINARY '%$pro%' AND '$za'= BINARY za $AND
    ");
    list($n[$last],$poradi,$pocet)= pdo_fetch_row($res);
    if ( $pocet==0 ) {
      $err= "cena pro $proza není v ceníku definovaná"; goto end;
    }
    elseif ( $pocet>1 ) {
      $err= "cena pro $proza není v ceníku jednoznačná (řádky $poradi)"; goto end;
    }
    $ns= 1;
    for ($i= 0; $i<=$last; $i++) {
      $x=           $n[$i]=='N'
        ? $noci : ( $n[$i]=='O'
        ? $obedu
        : $n[$i]);
      $ns*= $x;
    }
    $cena+= $sign=='-' ? -$ns : $ns;
                                                display(" $sign $term=$ns ... $cena ");
  }
//                                                 debug($terms,$count);
end:
  $html= "<br><br>$expr (věk $vek let) ";
  $html.= $err
    ? "<div style='color:red'>$err</div>"
    : "= <b>$cena,-</b> <br><br><i>(N=$noci je počet nocí a menu, O=$obedu je počet obědů)</i>";
  return $html;
}
# -------------------------------------------------------------------------------- akce2 vzorec_test
# test výpočtu platby za pobyt na akci 
function akce2_vzorec_test($id_akce,$hnizdo=0,$nu=2,$nD=0,$nd=0,$nk=0,$np=0,$table_class='') {  trace();
  $ret= (object)array('navrh'=>'','cena'=>0,'err'=>'');
  $map_typ= map_cis('ms_akce_ubytovan','zkratka');
  $types= select("GROUP_CONCAT(DISTINCT typ ORDER BY typ)","cenik",
      "id_akce=$id_akce AND hnizdo=$hnizdo GROUP BY id_akce");
  if (!$types) $types= '0';
  // obecné info o akci
  list($ma_cenik,$noci,$strava_oddo)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
                                                display("$ma_cenik,$noci,$strava_oddo - typy:$types ");
  if ( !$ma_cenik ) { $html= "akce nemá ceník"; goto end; }
  // definované položky
  $o= $strava_oddo=='oo' ? 1 : 0;       // oběd navíc
  $cenik= array(
    //            u p D d k noci oo plus
    'Nl' => array(1,0,1,0,0,   1, 0,  1),
    'Np' => array(0,1,1,1,0,   1, 0,  1),
    'K'  => array(1,0,0,0,0,   1, 0,  1),
    'P'  => array(1,0,0,0,0,   0, 0,  1),
    'PD' => array(0,0,1,0,0,   0, 0,  1),
    'Pd' => array(0,0,0,1,0,   0, 0,  1),
    'Pk' => array(0,0,0,0,1,   0, 0,  1),
    'Su' => array(1,0,0,0,0,   0, 0, -1),
    'Sk' => array(0,0,0,0,1,   0, 0, -1),
    'sc' => array(1,1,0,0,0,   1, 0,  1),
    'oc' => array(1,1,0,0,0,   1,$o,  1),
    'vc' => array(1,1,0,0,0,   1, 0,  1),
    'sp' => array(0,0,1,1,0,   1, 0,  1),
    'op' => array(0,0,1,1,0,   1,$o,  1),
    'vp' => array(0,0,1,1,0,   1, 0,  1),
  );
  // výpočet ceny podle parametrů jednotlivých typů (jsou-li)
  foreach(explode(',',$types) as $typ) {
                                                display("typ:$typ, hnízdo:$hnizdo ");
    $title= $typ ? "<h3>ceny pro ".$map_typ[$typ]."</h3>" : '';
    $cena= 0;
    $html.= "$title<table class='$table_class'>";
    $ra= pdo_qry("SELECT * FROM cenik 
      WHERE id_akce=$id_akce AND hnizdo=$hnizdo AND za!='' AND typ='$typ' ORDER BY poradi");
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $acena= $a->cena;
      list($za_u,$za_up,$za_D,$za_d,$za_k,$za_noc,$oo,$plus)= $cenik[$a->za];
      $nx= $nu*$za_u + $np*$za_up + $nD*$za_D + $nd*$za_d + $nk*$za_k;
      $cena+= $cc= $nx * ($za_noc?$noci+$oo:1) * $acena * $plus;
      if ( $cc ) {
        $pocet= $za_noc?" * ".($noci+$oo):'';
        $html.= "<tr>
          <td>{$a->polozka} ($nx$pocet * $acena)</td>
          <td align='right'>$cc</td></tr>";
      }
    }
    $html.= "<tr><td><b>Celkem</b></td><td align='right'><b>$cena</b></td></tr>";
    $html.= "</table>";
  }
  // návrat
end:
  $ret->cena= $cena;
  $ret->navrh= $html;
  return $ret;
}
# ------------------------------------------------------------------------------ akce2 vzorec_soubeh
# výpočet platby za pobyt na hlavní akci, včetně platby za souběžnou akci (děti)
# pokud je $id_pobyt=0 provede se výpočet podle dodaných hodnot (dosp+koje)
function akce2_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna,$dosp=0,$deti=0,$koje=0) { trace();
  // načtení ceníků
  $sleva= 0;
  $ret= (object)array('navrh'=>'','err'=>'','naklad_d'=>0,'poplatek_d'=>0);
  $dite_kat= xx_akce_dite_kat($id_hlavni);
  $map_kat= map_cis($dite_kat,'zkratka');
    $Kc= "&nbsp;Kč";
  $hnizdo= 0;
  if ( $id_pobyt ) {
    // zjištění parametrů pobytu podle hlavní akce
    $qp= "SELECT * FROM pobyt AS p JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
    $rp= pdo_qry($qp);
    if ( $rp && ($p= pdo_fetch_object($rp)) ) {
      $pocetdnu= $p->pocetdnu;
      $strava_oddo= $p->strava_oddo;
      $dosp= $p->pouze ? 1 : 2;
      $sleva= $p->sleva;
  //     $svp= $p->svp;
  //     $neprijel= $p->funkce==10;
      $datum_od= $p->datum_od;
    }
    // pokud mají děti označenou kategorii X, určuje se cena podle pX ceníku souběžné akce
    // cena pro dospělé se určí podle ceníku hlavní akce - děti bez kategorie se nesmí
    $deti_kat= array();
    $n= $ndeti= $chuv= 0;
    $qo= "SELECT o.jmeno,s.dite_kat,p.funkce, t.role, p.ubytovani, narozeni, p.funkce, p.hnizdo,
           s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
            FROM pobyt
            JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
            JOIN osoba ON osoba.id_osoba=spolu.id_osoba
            WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
          FROM spolu AS s
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          WHERE id_pobyt=$id_pobyt";
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $hnizdo= $o->hnizdo;
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      $kat= $o->dite_kat;
      $pps= $o->funkce==1;
      if ( $o->role=='d' ) {
        if ( $kat ) {
          // dítě - speciální cena
          $deti_kat[$map_kat[$kat]]++;
          $ndeti++;
        }
        elseif ( $vek<18 ) {
          // dítě bez kategorie
          $ret->err.= "<br>Chybí kategorie u dítěte: {$o->jmeno}";
          $ndeti++;
        }
        if ( $o->_chuva ) {
          list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
          if ( $pobyt!=$id_pobyt ) {
            // chůva nebydlí s námi ale platíme ji
            $chuv++;
          }
          else {
            // chůva bydlí s námi a platíme ji
            $chuv++;
          }
        }
      }
      $n++;
    }
    // kontrola počtu
    if ( $dosp + $chuv + $ndeti != $n ) {
      $ret->err.= "<br>chyba v počtech: dospělí $dosp + chůvy $chuv + děti $ndeti není celkem $n";
    }
  }
  elseif ( $id_hlavni ) {
    // zjištění parametrů testovacího "pobytu" podle ceníku dané akce
    list($pocetdnu,$strava_oddo)=
      select("DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_hlavni");
    // doplnění dětí do tabulky jako kojenců
    if ( $koje ) $deti_kat['F']= $koje;
    if ( $deti ) $deti_kat['B']= $deti;
  }
  $dosp_chuv= $dosp+$chuv;
//                                         debug($deti_kat,"dětí");
  // načtení ceníků
  $cenik_dosp= $cenik_deti= array();
  akce2_nacti_cenik($id_hlavni,$hnizdo,$cenik_dosp,$ret->navrh);   if ( $ret->navrh ) goto end;
  akce2_nacti_cenik($id_soubezna,$hnizdo,$cenik_deti,$ret->navrh); if ( $ret->navrh ) goto end;
  // redakce textu k ceně dospělých
  $Kc= "&nbsp;Kč";
  $html.= "<b>Rozpis platby za účast dospělých na jejich akci</b><table>";
  $cena= 0;
  $ubytovani= $strava= $program= $slevy= '';
  foreach($cenik_dosp as $za=>$a) {
    $c= $a->c; $txt= $a->txt;
    switch ($za) {
    case 'Nl':
      $cena+= $cc= $dosp_chuv * $pocetdnu * $c;
      if ( !$cc ) break;
      $ret->c_nocleh+= $cc;
      $ubytovani.= "<tr><td>".($dosp_chuv*$pocetdnu)." x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'P':
      $cena+= $cc= $c * $dosp;
      if ( !$cc ) break;
      $ret->c_program+= $cc;
      $program.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Su':
      if ( $pps ) continue 2;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Sp':
    case 'Sv':
      if ( !$pps ) continue 2;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'sc': case 'oc': case 'vc':
      $strav= $dosp_chuv * ($pocetdnu + ($za=='oc' && $strava_oddo=='oo' ? 1 : 0)); // případně oběd navíc
      $cena+= $cc= $strav * $c;
      if ( !$cc ) break;
      $ret->c_strava+= $cc;
      $html.= "<tr><td>$strav x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    default:
      $ret->err.= "<br>cenu za $za nelze vypočítat";
    }
  }
  // doplnění slev
  if ( $sleva ) {
    $cena-= $sleva;
    $ret->c_sleva-= $sleva;
    $slevy.= "<tr><td>zvláštní sleva</td><td align='right'>$sleva$Kc</td></tr>";
  }
  // konečná redakce textu k ceně dospělých
  if ( $ubytovani ) $html.= "<tr><th>ubytování</th></tr>$ubytovani";
  if ( $strava )    $html.= "<tr><th>strava</th></tr>$strava";
  if ( $program )   $html.= "<tr><th>program</th></tr>$program";
  if ( $slevy )     $html.= "<tr><th>sleva</th></tr>$slevy";
  $html.= "<tr><th>Celkem za dospělé</th><th align='right'>$cena$Kc</th></tr>";
  $html.= "</table>";
  // redakce textu k ceně dětí
  $sleva= "";
  if ( count($deti_kat) ) {
    $html.= "<br><b>Rozpis platby za účast dětí na jejich akci</b><table>";
    $cena= 0;
    ksort($deti_kat);
    foreach($deti_kat as $kat=>$n) {
      $a= $cenik_deti["p$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= $c * $n;
      $ret->naklad_d+= $cc;
      $html.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
      $a= $cenik_deti["d$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= - $c * $n;
      $ret->poplatek_d+= $cc;
      $sleva.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
    }
    $ret->poplatek_d+= $ret->naklad_d;
    $html.= "<tr><th>sleva</th></tr>$sleva";
    $html.= "<tr><th>Celkem za děti</th><th align='right'>$cena$Kc</th></tr>";
    $html.= "</table>";
  }
end:
  if ( $ret->err ) $ret->navrh.= "<b style='color:red'>POZOR! neúplná platba:</b>{$ret->err}<hr>";
  $ret->navrh.= $html;
  $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
//                                                         debug($ret,"akce_vzorec_soubeh");
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 nacti_cenik
# lokální pro akce2_vzorec_soubeh a tisk2_sestava_lidi
function akce2_nacti_cenik($id_akce,$hnizdo,&$cenik,&$html) {
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce AND hnizdo=$hnizdo ORDER BY poradi";
  $ra= pdo_qry($qa);
  if ( !pdo_num_rows($ra) ) {
    $html.= "akce $id_akce nemá ceník";
  }
  else {
    $cenik= array();
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $za= $a->za;
      if ( !$za ) continue;
      $cc= (object)array();
      if ( isset($cenik[$za]) ) $html.= "v ceníku se opakují kódy za=$za";
      $cenik[$za]= (object)array('c'=>$a->cena,'txt'=>$a->polozka);
    }
//                                                        debug($cenik,"ceník pro $id_akce");
  }
}
# ------------------------------------------------------------------------------------- akce2 vzorec
# výpočet platby za pobyt na akci
# od 130416 přidána položka CENIK.typ - pokud je 0 tak nemá vliv,
#                                       pokud je nenulová pak se bere hodnota podle POBYT.ubytovani
function akce2_vzorec($id_pobyt) {  //trace();
  // případné přepnutí na ceník verze 2017
  list($id_akce,$cenik_verze,$ma_cenik,$ma_cenu)= select(
    "id_akce,ma_cenik_verze,ma_cenik,ma_cenu",
    "pobyt JOIN akce ON id_akce=id_duakce","id_pobyt=$id_pobyt");
//  if ( $cenik_verze==1 ) return akce2_vzorec_2017($id_pobyt,$id_akce,2017);
  $ok= true;
  $ret= (object)array(
      'navrh'=>'cenu nelze spočítat',
      'c_sleva'=>0,
      'eko'=>(object)array(
          'vzorec'=>(object)array(),
          'slevy'=>(object)array('kc'=>0)
      ));
  if (!$ma_cenik && !$ma_cenu) {
    $ret->navrh= "akce nemá ani ceník ani jednotnou cenu (karta AKCE)";
    goto end; // další výpočet nemá smysl
  }
  // parametry pobytu
  $x= (object)array();
  $ubytovani= 0;
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= pdo_qry($qp);
  if ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $x->nocoprist+= $p->pristylky * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $ubytovani= $p->ubytovani;
    $sleva= $p->sleva;
    $svp= $p->svp;
    $neprijel= $p->funkce==10 || $p->funkce==14;
    $datum_od= $p->datum_od;
    $hnizda= $p->hnizda ? 1 : 0;
  }
  // podrobné parametry, ubytovani ma hodnoty z číselníku ms_akce_ubytovan
  // děti: koje=do 3 let | male=od 3 do 6 | velke=nad 6
  $dosp= $deti_male= $deti_velke= $koje= $chuv= $dite_male_chovane= $dite_velke_chovane= $koje_chovany= 0;
  $chuvy= $del= '';
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role, p.ubytovani,p.hnizdo,
         s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
        WHERE id_pobyt=$id_pobyt
        GROUP BY o.id_osoba";
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
    if ( $o->role=='d' ) {
      if ( $o->pecovane ) {
        $chuv++;
      }
      elseif ( $vek<3 ) {
        $koje++;
        if ( $o->_chuva ) $koje_chovany++;
      }
      elseif ( $vek<6 ) {
        $deti_male++;
        if ( $o->_chuva ) $dite_male_chovane++;
      }
      else {
        $deti_velke++;
        if ( $o->_chuva ) $dite_velke_chovane++;
      }
      if ( $o->_chuva ) {
        list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
        if ( $pobyt!=$id_pobyt ) {
          // chůva nebydlí s námi ale platíme ji
          $chuvy= "$del$jmeno $prijmeni";
          $del= ' a ';
        }
        else {
          // chůva bydlí s námi a platíme ji
        }
      }
    }
    elseif ($vek>18) {
      $dosp++;
    }
  }
//                                                         debug($x,"pobyt");
  // zpracování strav
//  $strava= akce2_strava_pary($id_akce,'','','',true,$id_pobyt);
  $strava= akce2_strava($id_akce,(object)array(),'','',true,0,$id_pobyt);
//                                                         debug($strava,"strava"); 
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidlo= substr($jidlo,0,2);
    $jidel->$jidlo+= $pocet;
  }
//                                                         debug($jidel,"strava"); goto end;
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= pdo_qry($qry);
  if ( $res && $c= pdo_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
    $ret->eko->slevy= $vzor->slevy;
  }
//                                                         debug($vzor);
  // načtení ceníku do pole $cenik s případnou specifikací podle typu ubytování
  $AND_hnizdo= $hnizda ? "AND hnizdo=$p->hnizdo" : '';
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce $AND_hnizdo ORDER BY poradi";
  $ra= pdo_qry($qa);
  $n= $ra ? pdo_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    $cenik_typy= false;
    $nazev_ceniku= '';
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $cc= (object)array();
      // diskuse pole typ - je-li nenulové, ignorují se hodnoty různé od POBYT.ubytovani
      if ( $a->typ!=0 && $a->typ!=$ubytovani ) continue;
      $cc->typ= $a->typ;
      if ( $a->typ && !$cenik_typy ) {
        // ceník má typy a tedy pobyt musí mít definované nenulové ubytování
        if ( !$ubytovani ) {
          $html.= "účastník nemá definován typ ubytování, ačkoliv to ceník požaduje";
          $ok= false;
        }
        $cenik_typy= true;
      }
      $pol= $a->polozka;
      if ( $cenik_typy && substr($pol,0,1)=='-' && substr($pol,1,1)!='-' ) {
        // název typu ceníku
        $nazev_ceniku= substr($pol,1);
      }

      $cc->txt= $pol;
      $cc->za= $a->za;
      $cc->c= $a->cena;
      $cc->j= $a->za ? $jidel->{$a->za} : '';
      $cenik[]= $cc;
    }
//                                                         debug($cenik,"ceník pro typ $ubytovani");
  }
  // výpočty
  if ( $ok ) {
    $nl= $x->nocoluzka;
    $np= $x->nocoprist;
    $u= $ucastniku;
    $cena= 0;
    $html.= "<table>";
    // ubytování
    $html.= "<tr><th>ubytování $nazev_ceniku</th></tr>";
    $ret->c_nocleh= 0;
    if ( $vzorec && $vzor->slevy->ubytovani===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
      switch ($a->za) {
        case 'Nl':
          $cc= $nl * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'Np':
          $cc= $np * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($np*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'K':
          $poplatku= $dosp * $p->pocetdnu;
          $cc= $poplatku * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($poplatku*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_nocleh}</th></tr>";
    }
    // strava
    $html.= "<tr><th>strava</th></tr>";
    $ret->c_strava= 0;
    if ( $vzorec && $vzor->slevy->strava===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        if ( $a->j ) switch ($a->za) {
        case 'sc': case 'sp': case 'oc':
        case 'op': case 'vc': case 'vp':
          $cc= $a->j * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_strava+= $cc;
          $html.= "<tr><td>{$a->txt} ({$a->j}*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_strava}</th></tr>";
    }
    // program
    $html.= "<tr><th>program</th></tr>";
    $ret->c_program= 0;
    if ( $vzorec && $vzor->slevy->program===0 ) {
      $html.= "<tr><td>program</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        switch ($a->za) {
        case 'P':
          $cc= $a->c * $u;
          $cena+= $cc;
          $ret->c_program+= $cc;
          $ret->eko->vzorec->{$a->za}+= $cc;
          $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          break;
        case 'Pd': // pro děti malé 3-6 let
          if ( $deti_male - $dite_male_chovane > 0 ) {
            $cc= $a->c * ($deti_male-$dite_male_chovane);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'PD': // pro děti velké > 6 let
          if ( $deti_velke - $dite_velke_chovane > 0 ) {
            $cc= $a->c * ($deti_velke-$dite_velke_chovane);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'Pk':
          if ( $koje - $koje_chovany > 0 ) {
            $cc= $a->c * ($koje-$koje_chovany);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_program}</th></tr>";
    }
    // případné slevy
    $ret->c_sleva= 0;
    $sleva_cenik= 0;
    $sleva_cenik_html= '';
    foreach ($cenik as $a) {
      switch ($a->za) {
      case 'Su':        // sleva na dospělého účastníka
        $sleva_cenik+= $u * $a->c;
        $sleva_cenik_html.= '';
        break;
      case 'Sk':        // sleva na kojence
        $sleva_cenik+= $koje * $a->c;
        $sleva_cenik_html.= '';
        break;
      }
    }
    $sleva+= $sleva_cenik;
    if ( !$neprijel && ($sleva!=0 || isset($vzor->slevy->procenta) || isset($vzor->slevy->za)) ) {
      $html.= "<tr><th>slevy</th></tr>";
      if ( $sleva!=0 ) {
        $cena-= $sleva;
        $ret->c_sleva-= $sleva;
//        if ( !isset($ret->eko) ) $ret->eko= (object)array();
//        if ( !isset($ret->eko->slevy) ) $ret->eko->slevy= (object)array();
        if ( !isset($ret->eko->slevy->kc) ) $ret->eko->slevy->kc= 0;
//        if ( !isset($ret->eko->slevy->kc) ) { debug($ret); return $ret; }
        $ret->eko->slevy->kc+= $sleva;
        $html.= "<tr><td>sleva z ceny</td><td align='right'>$sleva</td></tr>";
      }
      if ( isset($vzor->slevy->procenta) ) {
        $cc= -round($cena * $vzor->slevy->procenta/100,-1);
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->procenta}%</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->castka) ) {
        $cc= -$vzor->slevy->castka;
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->castka},-</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->za) ) {
        $cc= 0;
        foreach ($cenik as $radek) {
          if ( $radek->za==$vzor->slevy->za ) {
            $cc= -$radek->c;
            break;
          }
        }
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} </td><td align='right'>$cc</td></tr>";
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_sleva}</th></tr>";
    }
    $html.= "<tr><th>celkový poplatek</th><td></td><th align='right'>$cena</th></tr>";
    if ( $chuvy ) {
      $html.= "<tr><td colspan=3>(Cena obsahuje náklady na vlastního pečovatele: $chuvy)</td></tr>";
    }
    $html.= "</table>";
    $ret->navrh= $html;
    $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
  }
  else {
    $ret->navrh.= ", protože $html";
  }
end:  
  return $ret;
}//akce2_vzorec
# -------------------------------------------------------------------------------- akce2 vzorec_2017
# výpočet platby za pobyt na akci
# od 130416 přidána položka CENIK.typ - pokud je 0 tak nemá vliv,
#                                       pokud je nenulová pak se bere hodnota podle POBYT.ubytovani
function __akce2_vzorec_2017($id_pobyt,$id_akce,$verze=2017) {  //trace();
  // případné přepnutí na ceník verze 2017
  list($id_akce,$cenik_verze)= select(
    "id_akce,ma_cenik_verze","pobyt JOIN akce ON id_akce=id_duakce","id_pobyt=$id_pobyt");
  //   if ( $cenik_verze==1 ) return akce2_vzorec_2017($id_pobyt,$id_akce);                           !!!
  $ok= true;
  $ret= (object)array('navrh'=>'cenu nelze spočítat','eko'=>(object)array('vzorec'=>(object)array()));
  // parametry pobytu
  $x= (object)array();
  $ubytovani= $noci= 0;
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= pdo_qry($qp);
  if ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $noci= $p->pocetdnu;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $x->nocoprist+= $p->pristylky * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $ubytovani= $p->ubytovani;
    $sleva= $p->sleva;
    $svp= $p->svp;
    $neprijel= $p->funkce==10 || $p->funkce==14;
    $datum_od= $p->datum_od;
  }
  // podrobné parametry, ubytovani ma hodnoty z číselníku ms_akce_ubytovan
  $deti= $koje= $chuv= $dite_chovane= $koje_chovany= 0;
  $chuvy= $del= '';
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role, p.ubytovani,
         s.pecovane,MAX((SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba)) AS _chuva
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
        WHERE id_pobyt=$id_pobyt
        GROUP BY o.id_osoba";
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    if ( $o->role=='d' ) {
      // zjištění věku k začátku akce
      $vek= $verze==2017
        ? roku_k($o->narozeni,$datum_od)
        : narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
                                                display("$o->jmeno $vek");
      if ( $vek>=3 && $vek<6 ) {
        // ve vzorci 2017 počítáme nocolůžka jen dětem starším 6 let
        $x->nocoluzka-= $noci;
      }
      if ( $vek<3 ) {
        $koje++;
        if ( $o->_chuva ) $koje_chovany++;
      }
      else {
        $deti++;
        if ( $o->_chuva ) $dite_chovane++;
      }
      if ( $o->_chuva ) {
        list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
        if ( $pobyt!=$id_pobyt ) {
          // chůva nebydlí s námi ale platíme ji
          $chuvy.= "$del$jmeno $prijmeni pro dítě $o->jmeno";
          $del= ' a ';
        }
        else {
          // chůva bydlí s námi a platíme ji
          $chuvy.= "$del$jmeno $prijmeni  pro dítě $o->jmeno";
          $del= ' a ';
        }
      }
      if ( $o->pecovane ) {
        $chuv++;
      }
    }
  }
  //                                                         debug($x,"pobyt");
  // zpracování strav
  $strava= akce2_strava_pary($id_akce,'','','',true,$id_pobyt);
  //                                                         debug($strava,"strava");
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidel->$jidlo+= $pocet;
  }
  //                                                         debug($jidel,"strava");
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= pdo_qry($qry);
  if ( $res && $c= pdo_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
    $ret->eko->slevy= $vzor->slevy;
  }
  //                                                         debug($vzor);
  // načtení ceníku do pole $cenik s případnou specifikací podle typu ubytování
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= pdo_qry($qa);
  $n= $ra ? pdo_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    $cenik_typy= false;
    $nazev_ceniku= '';
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $cc= (object)array();
      // diskuse pole typ - je-li nenulové, ignorují se hodnoty různé od POBYT.ubytovani
      if ( $a->typ!=0 && $a->typ!=$ubytovani ) continue;
      $cc->typ= $a->typ;
      if ( $a->typ && !$cenik_typy ) {
        // ceník má typy a tedy pobyt musí mít definované nenulové ubytování
        if ( !$ubytovani ) {
          $html.= "účastník nemá definován typ ubytování, ačkoliv to ceník požaduje";
          $ok= false;
        }
        $cenik_typy= true;
      }
      $pol= $a->polozka;
      if ( $cenik_typy && substr($pol,0,1)=='-' && substr($pol,1,1)!='-' ) {
        // název typu ceníku
        $nazev_ceniku= substr($pol,1);
      }

      $cc->txt= $pol;
      $cc->za= $a->za;
      $cc->c= $a->cena;
      $cc->j= $a->za ? $jidel->{$a->za} : '';
      $cenik[]= $cc;
    }
  //                                                         debug($cenik,"ceník pro typ $ubytovani");
  }
  // výpočty
  if ( $ok ) {
    $nl= $x->nocoluzka;
    $np= $x->nocoprist;
    $u= $ucastniku;
    $cena= 0;
    $html.= "<table>";
    // ubytování
    $html.= "<tr><th>ubytování $nazev_ceniku</th></tr>";
    $ret->c_nocleh= 0;
    if ( $vzorec && $vzor->slevy->ubytovani===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
      switch ($a->za) {
        case 'Nl':
          $cc= $nl * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'Np':
          $cc= $np * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($np*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_nocleh}</th></tr>";
    }
    // strava
    $html.= "<tr><th>strava</th></tr>";
    $ret->c_strava= 0;
    if ( $vzorec && $vzor->slevy->strava===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        if ( $a->j ) switch ($a->za) {
        case 'sc': case 'sp': case 'oc':
        case 'op': case 'vc': case 'vp':
          $cc= $a->j * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_strava+= $cc;
          $html.= "<tr><td>{$a->txt} ({$a->j}*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_strava}</th></tr>";
    }
    // program
    $html.= "<tr><th>program</th></tr>";
    $ret->c_program= 0;
    if ( $vzorec && $vzor->slevy->program===0 ) {
      $html.= "<tr><td>program</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        switch ($a->za) {
        case 'P':
          $cc= $a->c * $u;
          $cena+= $cc;
          $ret->c_program+= $cc;
          $ret->eko->vzorec->{$a->za}+= $cc;
          $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          break;
        case 'Pd':
          if ( $deti - $dite_chovane - $chuv > 0 ) {
            $cc= $a->c * ($deti-$dite_chovane-$chuv);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'Pk':
          if ( $koje - $koje_chovany > 0 ) {
            $cc= $a->c * ($koje-$koje_chovany);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_program}</th></tr>";
    }
    // případné slevy
    $ret->c_sleva= 0;
    $sleva_cenik= 0;
    $sleva_cenik_html= '';
    foreach ($cenik as $a) {
      switch ($a->za) {
      case 'Su':        // sleva na dospělého účastníka
        $sleva_cenik+= $u * $a->c;
        $sleva_cenik_html.= '';
        break;
      case 'Sk':        // sleva na kojence
        $sleva_cenik+= $koje * $a->c;
        $sleva_cenik_html.= '';
        break;
      }
    }
    $sleva+= $sleva_cenik;
    if ( !$neprijel && ($sleva!=0 || isset($vzor->slevy->procenta) || isset($vzor->slevy->za)) ) {
      $html.= "<tr><th>slevy</th></tr>";
      if ( $sleva!=0 ) {
        $cena-= $sleva;
        $ret->c_sleva-= $sleva;
        if ( !isset($ret->eko->slevy) ) $ret->eko->slevy= (object)array();
        $ret->eko->slevy->kc+= $sleva;
        $html.= "<tr><td>sleva z ceny</td><td align='right'>$sleva</td></tr>";
      }
      if ( isset($vzor->slevy->procenta) ) {
        $cc= -round($cena * $vzor->slevy->procenta/100,-1);
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->procenta}%</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->castka) ) {
        $cc= -$vzor->slevy->castka;
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->castka},-</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->za) ) {
        $cc= 0;
        foreach ($cenik as $radek) {
          if ( $radek->za==$vzor->slevy->za ) {
            $cc= -$radek->c;
            break;
          }
        }
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} </td><td align='right'>$cc</td></tr>";
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_sleva}</th></tr>";
    }
    $html.= "<tr><th>celkový poplatek</th><td></td><th align='right'>$cena</th></tr>";
    if ( $chuvy ) {
      $html.= "<tr><td colspan=3>(Cena obsahuje náklady na vlastního pečovatele: $chuvy)</td></tr>";
    }
    $html.= "</table>";
    $ret->navrh= $html;
    $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
  }
  else {
    $ret->navrh.= ", protože $html";
  }
  return $ret;
}//akce2_vzorec_2017

//# ------------------------------------------------------------------------------ akce2 vzorec_2017_0
//# EXPERIMENT - ZATÍM NEZAPOJENO
//# výpočet platby za pobyt na akci pro ceníky verze 2017 (bez cenik.typ)
//function akce2_vzorec_2017_0($id_pobyt,$id_akce) {  //trace();
//  $ret= (object)array(
//    'err'=>'',
//    'navrh'=>'cenu nelze spočítat',
//    'eko'=>(object)array('vzorec'=>(object)array()),
//    'c_nocleh'=>0, 'c_strava'=>0, 'c_program'=>0, 'c_sleva'=>0
//  );
//
//  // klasifikace položek
//  $za_stravu=    " sSoOvV";
//  $za_ubytovani= " n";
//  $za_program=   " P Pk Pd";
//
//  // informace o akci
//  list($noci,$strava_oddo,$datum_od)=
//    select("DATEDIFF(datum_do,datum_od),strava_oddo,datum_od","akce","id_duakce=$id_akce");
//  $obedu= $noci + ($strava_oddo=='oo' ? 1 : 0);
//
//  // načtení řádků ceníku
//  $radek= array();
//  $rc= pdo_qry("SELECT poradi,polozka,za,cena FROM cenik WHERE id_akce=$id_akce ORDER BY poradi");
//  while ($rc && (list($poradi,$polozka,$za,$kc)=pdo_fetch_row($rc)) ) {
//    $radek[$poradi]= (object)array('tx'=>$polozka,'kc'=>$kc,'za'=>$za,'n'=>0);
//  }
//
//  // probereme lidi, za které se platí
//  $chuvy= $del= '';
//  $vzorec= 0;   // vzorec pro slevy
//  $sleva= 0;    // zvláštní sleva pro pobyt
//  $rs= pdo_qry("
//    SELECT p.pocetdnu,p.vzorec,p.sleva,o.prijmeni,o.jmeno,c1.ikona,IFNULL(c2.barva,','),o.narozeni,
//      s.pecovane,sp.id_pobyt,
//      (SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
//        FROM pobyt
//        JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
//        JOIN osoba ON osoba.id_osoba=spolu.id_osoba
//        WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS chuva
//    FROM pobyt AS p
//      JOIN spolu AS s USING (id_pobyt)
//      JOIN osoba AS o USING (id_osoba)
//      LEFT JOIN spolu AS sp ON sp.id_osoba=s.pecovane AND sp.id_pobyt=p.id_pobyt
//      LEFT JOIN _cis AS c1 ON c1.druh='ms_akce_s_role'   AND c1.data=s.s_role
//      LEFT JOIN _cis AS c2 ON c2.druh='ms_akce_dite_kat' AND c2.data=s.dite_kat
//    WHERE p.id_pobyt=$id_pobyt
//    ORDER BY o.narozeni
//  ");
//  while ( $rs && (
//    list($dnu,$vzorec0,$sleva0,$prijmeni,$jmeno,$pro,$dite_kat,$narozeni,$pecovane,$pobyt_d,$chuva)
//      = pdo_fetch_row($rs)) ) {
//    $vzorec= $vzorec0;
//    $sleva= $sleva0;
//    // zjištění věku k začátku akce
//    if ( $dnu<$noci ) {
//      $noci= $obedu= $dnu;
//    }
//    $vek= $narozeni!='0000-00-00' ? roku_k($narozeni,$datum_od) : '?'; // výpočet věku
//    if ( $vek==='?' ) {
//      $ret->err= "k výpočtu ceny potřebuji znát věk pro '$jmeno $prijmeni'";
//      $ret->err.= "<br>vek=$vek";
//      $ret->err.= "<br>$narozeni!='0000-00-00'=".($narozeni!='0000-00-00'?1:0);
//      $ret->err.= "<br>roku_k($narozeni,$datum_od)=".roku_k($narozeni,$datum_od);
//      goto end;
//    }
////                                                         display("$jmeno $pro $vek $dite_kat");
//    // tvorba poznámky, pokud je chůva dítě z jiné rodiny (pobytu)
//    if ( $chuva ) {
//      list($prijmeni_ch,$jmeno_ch,$pobyt_ch)= explode(',',$chuva);
//      if ( $pobyt_ch!=$id_pobyt ) {
//        // chůva nebydlí s námi ale platíme ji
//        $chuvy.= "$del$jmeno_ch $prijmeni_ch pro $jmeno";
//        $del= ' a ';
//      }
//    }
//    // chůvu neplatí pokud pečuje o dítě z jiného pobytu
//    if ( $pecovane && $pobyt_d!=$id_pobyt ) continue;
//    // zjištění ceny z ceníku
//    $AND= isset($vek) ? "AND IF(od,$vek>=od,1) AND IF(do,$vek<do,1)" : '';
//    $rc= pdo_qry("
//      SELECT poradi
//      FROM cenik WHERE id_akce=$id_akce AND za!='' AND pro LIKE BINARY '%$pro%' $AND
//    ");
//    while ($rc && (list($poradi)=pdo_fetch_row($rc)) ) {
//      $za= $radek[$poradi]->za;
//      $radek[$poradi]->n+=
//        strpos(" n sSvV",$za) ? $noci  : (
//        strpos(" oO",$za)     ? $obedu : 1);
//    }
//  }
////                                                         debug($radek);
//
//  // zpracování strav
//  $strava= akce2_strava_pary($id_akce,'','','',true,$id_pobyt);
//  $jidel= (object)array();
//  foreach ($strava->suma as $den_jidlo=>$pocet) {
//    list($den,$jidlo)= explode(' ',$den_jidlo);
//    $jidel->$jidlo+= $pocet;
//  }
//  // překlad stravenek do verze 2017
//  $trans= array('S'=>'sc','s'=>'sp','O'=>'oc','o'=>'op','V'=>'vc','v'=>'vp');
//  foreach ($radek as $poradi=>$zan ) {
//    if (isset($trans[$zan->za]) ) {
//      $za= $trans[$zan->za];
//      $radek[$poradi]->n= $jidel->$za;
//    }
//  }
////                                                         debug($radek);
//
//  // načtení cenového vzorce pro slevy
//  $vzor= array();
//  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
//  $res= pdo_qry($qry);
//  if ( $res && $c= pdo_fetch_object($res) ) {
//    $vzor= $c;
//    $vzor->slevy= json_decode($vzor->ikona);
//    $ret->eko->slevy= $vzor->slevy;
//  }
//
//  // aplikace slev podle $vzor a sumarizace nákladů do položek c_* a příjmů do eko.vzorec
//  foreach ($radek as $poradi=>$p ) {
//    if ( !$p->za ) continue;
//    $cena= $p->n * $p->kc;
//    // ubytování
//    if ( strpos($za_ubytovani,$p->za) ) {
//      if ( $vzorec && $vzor->slevy->ubytovani===0 ) {
//        $ret->c_sleva+= $cena;
//        unset($radek[$poradi]);
//      }
//      else
//        $ret->c_nocleh+= $cena;
//    }
//    // strava
//    if ( strpos($za_stravu,$p->za) ) {
//      if ( $vzorec && $vzor->slevy->strava===0 ) {
//        $ret->c_sleva+= $cena;
//        unset($radek[$poradi]);
//      }
//      else
//        $ret->c_strava+= $cena;
//    }
//    // program
//    if ( strpos($za_program,$p->za) ) {
//      if ( $vzorec && $vzor->slevy->program===0 ) {
//        $ret->c_sleva+= $cena;
//        unset($radek[$poradi]);
//      }
//      else {
//        $ret->c_program+= $cena;
//        $ret->eko->vzorec->{$p->za}+= $cena;
//      }
//    }
//    // sleva: podle vzorec.slevy.za
//    if ( $vzorec && $vzor->slevy->za==$p->za ) {
//      $p->n= 1;
//      $ret->c_sleva+= $p->kc;
//    }
//    // sleva zvláštní: Sz
//    if ( $p->za=='Sz' && $sleva ) {
//      $p->n= 1;
//      $p->kc= -$sleva;
//      $ret->c_sleva+= -$sleva;
//    }
//  }
////                                                         debug($radek);
//
//  // optimalizace tabulky
//  krsort($radek);
//  $neco= 0;
//  foreach ($radek as $poradi=>$p ) {
//    if ( !$p->za ) {
//      // nadpis - smaž, když neco=0
//      if ( !$neco ) unset($radek[$poradi]);
//      $neco= 0;
//    }
//    elseif ( $p->n ) {
//      $neco++;
//    }
//    else
//      unset($radek[$poradi]);
//  }
//  ksort($radek);
//
//  // redakce textu
//  $sum= $subsum= 0;
//  $html= "<table>";
//  foreach ($radek as $poradi=>$p ) {
//    if ( $p->za ) {
//      // cena
//      $txt= "$p->n x $p->tx";
//      $sum+= $cena= $p->n * $p->kc;
//      $html.= "<tr><td>$txt</td><td align='right'>$cena</td></tr>";
//    }
//    else {
//      // nadpis
//      $html.= "<tr><th>".substr($p->tx,1)."</th></tr>";
//    }
//  }
//  $html.= "<tr><th>celkem</th><td align='right'><b>$sum</b></td></tr>";
//  // pokud je platba za chůvu z jiného pobytu, přidej poznámku
//  $html.= "</table>";
//  if ( $chuvy ) {
//    $html.= "<br>Cena obsahuje náklady na vlastního pečovatele $chuvy";
//  }
//
//  // $ret->mail         viz PHP:  mail2_personify
//  // $ret->eko          viz PHP:  akce2_text_eko
//  // $ret->navrh        viz Ezer: proc rozpis_ceny
//  // $ret->c_*|*_d      viz Ezer: c_nocleh, c_strava, c_program, c_sleva, poplatek_d, naklad_d
//  $ret->navrh= $html;
//  $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
//end:
////                                                        debug($ret);
//  if ( $ret->err ) $ret->navrh= $ret->err;
//  return $ret;
//}//akce2_vzorec_2017_0 EXPERIMENT

/** ========================================================================================> UCAST2 */
# --------------------------------------------------------------------------------- ucast2 clipboard
# vrácení mailů dospělých členů rodiny
function ucast2_clipboard($idp) {
  $y= (object)array('clip'=>'','msg'=>'funguje jen pro pobyt manželů s maily');
  $idr= select("i0_rodina","pobyt","id_pobyt=$idp");
  if ( !$idr ) goto end;
  list($n,$emaily)= select("COUNT(DISTINCT role),GROUP_CONCAT(DISTINCT IF(kontakt,email,''))",
      "pobyt JOIN spolu USING (id_pobyt) JOIN osoba USING (id_osoba) JOIN tvori USING (id_osoba)",
      "i0_rodina=$idr AND id_rodina=$idr AND role IN ('a','b') GROUP BY id_rodina");
  if ( $n==2 ) {
    if ( $_SESSION['browser']=='CH') {
      $y->clip= $emaily;
      $y->msg= "osobní maily byly zkopírovány do schránky:<br><br>$emaily<br><br>";
    }
    else {
      $y->msg= "osobní maily manželů (do schránky si zkopíruj):<br><br>$emaily<br><br>";
    }
  }
end:
//                                                        debug($y,$idp);
  return $y;
}
# ------------------------------------------------------------------------------ ucast2_pobyt_access
# ==> . chain rod
# účastníci pobytu a případná rodina budou mít daný access (3)
function ucast2_pobyt_access($idp,$access=3) {
  // změna pro rodinu
  $idr= select("i0_rodina","pobyt","id_pobyt=$idp");
  if ( $idr ) {
    $r_access= select("access","rodina","id_rodina=$idr");
    if ( $r_access!=$access ) {
      ezer_qry("UPDATE",'rodina',$idr,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
      ));
    }
  }
  // změna pro účastníky
  $qo= pdo_qry("SELECT access,id_osoba FROM spolu JOIN osoba USING (id_osoba) WHERE id_pobyt=$idp");
  while ( $qo && ($o= pdo_fetch_object($qo)) ) {
    $ido= $o->id_osoba;
    $o_access= $o->access;
    if ( $o_access!=$access ) {
      ezer_qry("UPDATE",'osoba',$ido,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o_access)
      ));
    }
  }
  return 1;
}
# --------------------------------------------------------------------------------- ucast2_chain_rod
# ==> . chain rod
# upozorní na pravděpodobnost duplicity rodiny
function ucast2_chain_rod($idro) { trace();
  $items2array= function($items,$omit='\s') {
    $items= preg_replace("/[$omit]/",'',$items);
    $arr= preg_split("/,;/",trim($items,",;"),-1,PREG_SPLIT_NO_EMPTY);
    return $arr;
  };
  $ret= (object)array('msg'=>'');
  if ( !$idro ) goto end;
  $nr= 0;
  $ro= (object)array();
  $rs= $x= array();
  $nazev= '';
  // srovnávané prvky
  $flds_r= "id_rodina,access,nazev,obec,emaily,telefony,datsvatba";
  $flds_o= "jmeno,kontakt,email,telefon,narozeni";
  // dotazovaná rodina
  $jmena= $telefony= $emaily= $narozeni= array();
  $qr= pdo_qry("SELECT $flds_r FROM rodina WHERE id_rodina=$idro");
  while ( $qr && ($rx= pdo_fetch_object($qr)) ) {
    $ro= $rx;
    $nazev= $rx->nazev;
    $emaily= $items2array($rx->emaily);
    $telefony= $items2array($rx->telefony);
    $qc= pdo_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= pdo_fetch_object($qc)) ) {
      $ro->cleni[]= $cx;
      $jmena[]= $cx->jmeno;
      if ( $cx->narozeni!='0000-00-00' ) $narozeni[]= $cx->narozeni;
      if ( $cx->kontakt ) {
        foreach($items2array($cx->email) as $em) {
          if ( !in_array($em,$emaily) )
            $emaily[]= $em;
        }
        foreach($items2array($cx->telefon,'^\d') as $tf) {
          if ( !in_array($tf,$telefony) )
            $telefony[]= $tf;
        }
      }
      $ro->telefony= $telefony;
      $ro->emaily= $emaily;
    }
  }
//                                                 debug($ro);
//   goto end;
  // . vzory faktorů
  // vyloučené shody
  $ids= select1("GROUP_CONCAT(DISTINCT val)","_track","kde='rodina' AND op='r' AND klic='$idro'");
  $ruzne= $ids ? "AND id_rodina NOT IN ($ids)" : "";
  // podobné rodiny
  $qr= pdo_qry("SELECT $flds_r FROM rodina
    WHERE nazev='$nazev' AND id_rodina!=$idro AND deleted='' $ruzne");
  while ( $qr && ($rx= pdo_fetch_object($qr)) ) {
    $rs[$rx->id_rodina]= $rx;
    $qc= pdo_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= pdo_fetch_object($qc)) ) {
      $rs[$rx->id_rodina]->cleni[]= $cx;
    }
  }
//                                                 debug($rs);
  // porovnání
  $nr= 0;
  foreach ($rs as $idr=>$rx) {
    $xi= (object)array('idr'=>$idr,'asi'=>'','orgs'=>0);
    $nt= $n_jmeno= $n_kontakt= $n_narozeni= 0;
    // . členové rodiny
    if ( $rx->cleni ) foreach ($rx->cleni as $cx) {
      $nt++;
      // .. jména
      if ( in_array($cx->jmeno,$jmena) )  $n_jmeno++;
      // .. kontakty
      if ( $cx->kontakt ) {
        foreach($items2array($cx->email) as $em) if ( in_array($em,$emaily) ) $n_kontakt++;
        foreach($items2array($cx->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $n_kontakt++;
      }
      // .. narození
      if ( in_array($cx->narozeni,$narozeni) )  $n_narozeni++;
    }
    $xi->cleni=    $n_jmeno;
    // . narození členů +1 * n
    $xi->narozeni= $n_narozeni;
    // . bydliště rodiny +5
    $xi->bydliste= $rx->obec==$ro->obec ? 1 : 0;
    // . svatba +10
    $xi->svatba= $rx->datsvatba!='0000-00-00' && $rx->datsvatba==$ro->datsvatba ? 1 : 0;
    // . kontakty +1 * n
    foreach($items2array($rx->email) as $em) if ( in_array($em,$emaily) ) $n_kontakt++;
    foreach($items2array($rx->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $n_kontakt++;
    $xi->kontakty= $n_kontakt;
    // organizace
    $xi->org= $rx->access;
    // zápis
    $x[$idr]= $xi;
    $nr++;
  }
  // míra podobnosti rodin
  $nx0= count($x);
  $idr= 0;
  $orgs= (int)$ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->svatba   ? 'S' : '';
    $xi->asi.= $xi->bydliste ? 'B' : '';
    $xi->asi.= $xi->cleni    ? 'C' : '';
    $xi->asi.= $xi->narozeni ? 'N' : '';
    $xi->asi.= $xi->kontakty ? 'K' : '';
    if ( !strlen($xi->asi) || $xi->asi=='C' ) { unset($x[$i]); continue; }
    $orgs|= (int)$xi->org;
    $idr= $i;
  }
//                                                 debug($x,"podobné rodiny");
  $nx= count($x);
  $msg= $dup= $keys= '';
  if ( $nx==0 ) {
    // není duplicita
    $msg= ($nx0 ? "asi " : "určitě ")." není duplicitní";
  }
  elseif ( $nx==1 ) {
    // jednoznačná duplicita
    $keys= $idr;
    $dup= $x[$idr]->asi;
    $msg= "pravděpodobně ($dup) duplicitní s rodinou $idr "
          . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
  }
  else {
    // více možností
    uasort($x,function($a,$b) { return $a->asi > $b->asi ? -1 : +1; });
    $msg= "je $nx pravděpodobných duplicit:";
    $del= '';
    foreach ($x as $i=>$xi) {
      $dupi= $xi->asi;
      if ( !$dup ) $dup= $dupi;
      $mira= strpos($dupi,'S')!==false || strpos($dupi,'K')!==false ? "pravděpodobně" : "možná";
      $msg.= "<br>$mira ($dupi) duplicitní s rodinou $i"
          . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
      $keys.= "$del$i";
      $del= ',';
    }
  }
//                                                 debug($x);
  $ret->msg= $msg;
  $ret->dup= $dup;
  $ret->keys= $keys;
end:
  return $ret;
}
# --------------------------------------------------------------------------------- ucast2_chain_oso
# ==> . chain oso
# upozorní na pravděpodobnost duplicity osoby
# pokud je zadáno $idr doplní i pravděpodobné duplicity v této rodině - na základě narození a jmen
function ucast2_chain_oso($idoo,$idr=0) {
  $items2array= function($items,$omit='\s') {
    $items= preg_replace("/[$omit]/",'',$items);
    $arr= preg_split("/,;/",trim($items,",;"),-1,PREG_SPLIT_NO_EMPTY);
    return $arr;
  };
  $ret= (object)array('msg'=>'','dup'=>'');
  $nr= 0;
  $o= (object)array();
  $os= $x= array();
  $nazev= '';
  // srovnávané prvky
  $flds= "id_osoba,o.access,prijmeni,jmeno,narozeni,kontakt,email,telefon,adresa,o.obec,sex";
  // dotazovaná osoba
  $jmena= $telefony= $emaily= array();
  $narozeni= '';
  $qo= pdo_qry("SELECT $flds FROM osoba AS o WHERE id_osoba=$idoo");
  if ( !$qo ) { $msg= "$idoo není osoba"; goto end; }
  $o= pdo_fetch_object($qo);
  $jmeno= $o->jmeno;
  $prijmeni= $o->prijmeni;
  if ( !trim($prijmeni) ) { goto end; }
  $obec= $o->adresa ? $o->obec : '';
  $emaily= $items2array($ox->email);
  $narozeni= $o->narozeni=='0000-00-00' ? '' : $o->narozeni;
  $narozeni_yyyy= substr($o->narozeni,0,4);
  $narozeni_11= substr($o->narozeni,5,5)=="01-01" ? $narozeni_yyyy : '';
  $sex= $o->sex;
  $emaily= $telefony= array();
  if ( $o->kontakt ) {
    $emaily= $items2array($o->email);
    $telefony= $items2array($o->telefon,'^\d');
  }
//                                                 debug($o,"originál");
  // . vzory faktorů
  // podobné osoby tzn. stejné příjmení
  $qo= pdo_qry("
    SELECT $flds,r.obec,
      SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
    FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
    WHERE (prijmeni='{$o->prijmeni}' /*OR rodne='{$o->prijmeni}'*/) AND id_osoba!=$idoo AND o.deleted=''
    GROUP BY id_osoba");
  while ( $qo && ($xo= pdo_fetch_object($qo)) ) {
    $xo_jmeno= trim($xo->jmeno);
//                                                 display($xo_jmeno);
    if ( $jmeno=='' || $xo_jmeno=='' ) continue;
    if ( strpos($xo_jmeno,$jmeno)===false && strpos($jmeno,$xo_jmeno)===false ) continue;
    // vymazání chybných údajů
    if ( !$xo->adresa ) $xo->obec= '?';
    if ( !$xo->kontakt ) $xo->telefon= $xo->email= '?';
    // doplnění rodinných údajů z kmenové rodiny, je-li
    if ( (!$xo->adresa || !$xo->kontakt) && $xo->_kmen) {
      $qr= pdo_qry("
        SELECT obec,telefony,emaily
        FROM rodina AS r WHERE id_rodina={$xo->_kmen}");
      if ( $qr && ($r= pdo_fetch_object($qr)) ) {
        if ( !$xo->adresa ) $xo->obec= $r->obec;
        if ( !$xo->kontakt ) {
          $xo->telefon= $r->telefony;
          $xo->email= $r->emaily;
        }
//                                                 debug($r,"kmen");
      }
    }
    $os[$xo->id_osoba]= $xo;
  }
  // podezřelí členi rodiny: jsou se stejným datem narození (nebo 1.1.rok kde rok=+-1 )
  // se stejným pohlavím
  $narozeni_od_do= ($narozeni_yyyy-1).','.($narozeni_yyyy).','.($narozeni_yyyy+1);
  if ( $idr ) {
    $qc= pdo_qry("
      SELECT id_osoba,prijmeni,jmeno,narozeni FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina=$idr AND deleted='' AND id_osoba!=$idoo AND sex=$sex
        AND (narozeni='$narozeni' OR DAY(narozeni)=1 AND MONTH(narozeni)=1
          AND YEAR(narozeni) IN ($narozeni_od_do))");
    while ( $qc && ($xc= pdo_fetch_object($qc)) ) {
      $idc= $xc->id_osoba;
      if ( !isset($os[$idc]) ) $os[$idc]= (object)array('id_osoba'=>$idc);
      $os[$idc]->narozeni= $xc->narozeni;
    }
  }
//                                                 debug($os,"kopie $idoo");
  // porovnání
  $nr= 0;
  foreach ($os as $ido=>$ox) {
    $xi= (object)array('ido'=>$ido,'asi'=>'','orgs'=>0);
    // .. kontakty
    $xi->kontakty= 0;
    if ( $ox->kontakt ) {
      foreach($items2array($ox->email) as $em) if ( in_array($em,$emaily) ) $xi->kontakty++;
      foreach($items2array($ox->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $xi->kontakty++;
    }
    // .. bydliste
    $xi->bydliste= $ox->adresa && $ox->obec==$obec && $obec ? 1 : 0;
    // .. narození
    $rok= substr($ox->narozeni,0,4);
    $xi->narozeni= $ox->narozeni==$narozeni
                || $rok==$narozeni_11
                || substr($ox->narozeni,5,5)=="01-01" && abs($narozeni_yyyy-$rok)<=1
                 ? 1 : 0;
//                                                 display("$ido:{$ox->jmeno}/$idoo:$jmeno: {$ox->narozeni}/$narozeni => {$xi->narozeni}");
    // organizace
    $xi->org= $ox->access;
    // zápis
    $x[$ido]= $xi;
  }
  // míra podobnosti osob
  $nx0= count($x);
  $i0= 0;
  $orgs= (int)$ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->bydliste ? 'b' : '';
    $xi->asi.= $xi->narozeni ? 'n' : '';
    $xi->asi.= $xi->kontakty ? 'k' : '';
    if ( !strlen($xi->asi) || $xi->asi=='b' ) { unset($x[$i]); continue; }
    $orgs|= (int)$xi->org;
    $i0= $i;
  }
//                                                 debug($x,"podobné osoby");
//                                                 goto end;
  $nx= count($x);
  $msg= $dup= $keys= '';
  if ( $nx==0 ) {
    // není duplicita
//     $msg= ($nx0 ? "asi " : "určitě ")." není duplicitní";
  }
  elseif ( $nx==1 ) {
    // jednoznačná duplicita
    // ujistíme se, že nebyli oznámeni jako různé tzv. _track(klic=idoo,op=r,val=idc)
    $r= select("COUNT(*)","_track","klic=$idoo AND op='r' AND val=$ido");
    if ( !$r ) {
      $dup= $x[$i0]->asi;
      $msg= "$idoo je pravděpodobně ($dup) kopie osoby $ido '$prijmeni $jmeno'"
            . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
      $keys= $i0;
    }
  }
  else {
    // více možností
    uasort($x,function($a,$b) { return $a->asi > $b->asi ? -1 : +1; });
    $msg= '';
    $del= '';
    $nx= 0;
    foreach ($x as $i=>$xi) {
      // ujistíme se, že nebyli oznámeni jako různé tzv. _track(klic=idoo,op=r,val=idc)
      $r= select("COUNT(*)","_track","klic=$idoo AND op='r' AND val=$i");
      if ( !$r ) {
        $nx++;
        $dupi= $xi->asi;
        if ( !$dup ) $dup= $dupi;
        $mira= strpos($dupi,'n')!==false || strpos($dupi,'k')!==false ? "pravděpodobně" : "možná";
        $msg.= "$del je $mira ($dupi) kopie  osoby $i"
            . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
        $keys.= "$del$i";
        $del= ',';
      }
    }
    $msg= $nx ? ( $nx==1 ? "$idoo$msg" : "$idoo má $nx pravděpodobných kopií:$msg" ) : '';
  }
  $ret->msg= $msg;
                                                if ( $msg ) display($msg);
  $ret->dup= $dup;
  $ret->keys= $keys;
end:
//                                                 debug($ret,$idoo);
  return $ret;
}
/** ------------------------------------------------------------------------------ ucast2 browse_ask **/
# BROWSE ASK
# obsluha browse s optimize:ask
#                                       !!! při změně je třeba ošetřit použití v sestavách
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou oddělovače řádků '≈' (oddělovač sloupců zůstává '~')
function ucast2_browse_ask($x,$tisk=false) {
  global $test_clmn,$test_asc, $y;
  // ofsety v atributech členů pobytu - definice viz níže
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, 
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta, $i_osoba_geo;
  $i_osoba_jmeno=     4;
  $i_osoba_vek=       6;
  $i_osoba_role=      9;
  $i_osoba_prijmeni= 15;
  $i_adresa=         18;
  $i_osoba_kontakt=  23;
  $i_osoba_telefon=  24;
  $i_osoba_email=    26;
  $i_osoba_obcanka=  32;
  $i_osoba_dieta=    40;
  $i_osoba_note=     42;
  $i_osoba_geo=      44;
  $i_key_spolu=      45;
  $i_spolu_dite_kat= 48;
  $i_spolu_note=     49;

  $delim= $tisk ? '≈' : '~';
  $map_umi= map_cis('answer_umi','zkratka','poradi','ezer_answer');
//                                                         debug($map_umi,"map_umi");
  $umi= function ($xs) use ($map_umi) {
    $y= '';
    if ( $xs ) foreach (explode(',',$xs) as $x) {
      $y.= $map_umi[$x];
    }
    return $y;
  };
//                                                         debug($x,"akce_browse_ask");
//                                                         return;
  $y= (object)array('ok'=>0);
  $neucasti= select1("GROUP_CONCAT(data)",'_cis',"druh='ms_akce_funkce' AND ikona=1");
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) {
    $y->$i= isset($x->$i) ? $x->$i : '';
  }
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
  default:
    # vnořené SQL definující @akce, @soubeh, @app
    if ( $x->sql ) 
      pdo_qry($x->sql);
    else 
      fce_error("browse_load - missing kontext");
    $pobyt= array();              // $pobyt[id_pobyt]             vše
    $skup= array();               // $skup[skupina]               seznam id_pobyt
    $osoba= array();              // $osoba[id_osoba]             atributy osob na akci
    $cleni= "";
    $osoby= "";
    $rodina= array();             // $rodina[id_rodina]           atributy rodin na akci
    $rodina_pobyt= array();       // $rodina[i0_rodina]=id_pobyt  pobyt rodiny (je-li rodinný)
    $rodiny= "";
    $spolu= array();              // $spolu[id_osoba]             id_pobyt
    # ladění
    $AND= "";
//     $AND= "AND p.id_pobyt IN (44285,44279,44280,44281) -- prázdná rodina a pobyt";
//     $AND= "AND p.id_pobyt IN (55772,55689) -- test";
//     $AND= "AND p.id_pobyt IN (43387,32218,32024) -- test";
//     $AND= "AND p.id_pobyt IN (43113,43385,43423) -- test Šmídkovi+Nečasovi+Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (43423) -- test Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (60350) -- Kordík";
//     $AND= "AND p.id_pobyt IN (20488,20344) -- Bajerovi a Kubínovi";
//     $AND= "AND p.id_pobyt IN (60594,60192) -- Němečkovi,Sapákovi";
//     $AND= "AND p.id_pobyt IN (60192) -- Sapákovi";
    # pro browse_row přidáme klíč
    if ( isset($x->subcmd) && $x->subcmd=='browse_row' ) {
      $AND= "AND p.id_pobyt={$y->oldkey}";
    }
    # duplicity, dokumenty, css?
    $duplicity= strstr($x->cond,'/*duplicity*/') ? 1 : 0;
    $dokumenty= strstr($x->cond,'/*dokumenty*/') ? 1 : 0;
    $barvit=    strstr($x->cond,'/*css*/')       ? 1 : 0;
    # podmínka
    $cond= $x->cond ?: 1;
    # atributy akce
    $qa= pdo_qry("
      SELECT @akce,@soubeh AS soubeh,@app,druh IN (1,2) AS _ms,
        datum_od,DATEDIFF(a.datum_do,a.datum_od)+1 AS dnu,ma_cenik,ma_cenu,cena
      FROM akce AS a
      WHERE a.id_duakce=@akce ");
    $akce= pdo_fetch_object($qa);
    # atributy pobytu
    $cond_p= str_replace("role IN ('a','b')","1",$cond);
    $ms1= $akce->_ms ? ",IFNULL(_ucasti._n,0)+IFNULL(r.r_ms,0) AS x_ms" : '';
    $ms2= $akce->_ms ? "
      LEFT JOIN (SELECT COUNT(*) AS _n,px.i0_rodina
        FROM pobyt AS px
        JOIN akce AS ax ON ax.id_duakce=px.id_akce
        WHERE ax.datum_od<='{$akce->datum_od}' AND ax.druh=1 AND ax.spec=0 AND ax.zruseno=0
          AND px.funkce NOT IN ($neucasti)
        GROUP BY  px.i0_rodina
      ) AS _ucasti ON _ucasti.i0_rodina=p.i0_rodina AND p.i0_rodina
    " : '';
    $uhrada1= $akce->ma_cenik==2 // cením Domu setkání
        ? "IFNULL(SUM(castka),0)"
        : "IFNULL(SUM(u_castka),0)";
    $uhrada2= $akce->ma_cenik==2 // cením Domu setkání
        ? "LEFT JOIN platba AS u ON id_pob=id_pobyt"
        : "LEFT JOIN uhrada AS u USING (id_pobyt)";
    $qp= pdo_qry("
      SELECT p.*,$uhrada1 AS uhrada,IFNULL(id_prihlaska,0) AS id_prihlaska $ms1
      FROM pobyt AS p
      $uhrada2
      LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      -- LEFT JOIN prihlaska AS pr USING (id_pobyt)
      LEFT JOIN (SELECT MAX(id_prihlaska) AS id_prihlaska,id_pobyt FROM prihlaska GROUP BY id_pobyt) AS pr USING (id_pobyt)
      $ms2
      WHERE $cond_p $AND
      GROUP BY p.id_pobyt
    ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
      $pobyt[$p->id_pobyt]= $p;
      $i0r= $p->i0_rodina;
      if ( $i0r ) {
        $rodina_pobyt[$i0r]= $p->id_pobyt;
        $pobyt[$p->id_pobyt]->access= $p;
        if ( !strpos(",$rodiny,",",$i0r,") )
          $rodiny.= ",$i0r";
      }
    }
//                                                         debug($pobyt[59518],"pobyt");
//                                                         debug($rodina_pobyt[2473],"rodina_pobyt");
    # seznam účastníků akce - podle podmínky
    $qu= pdo_qry("
      SELECT GROUP_CONCAT(id_pobyt) AS _ids_pobyt,s.*,o.narozeni,
        MIN(CONCAT(IF(IFNULL(role,'')='','?',role),id_rodina)) AS _role,o_umi,prislusnost
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      -- LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN tvori AS t ON t.id_osoba=s.id_osoba AND t.id_rodina=p.i0_rodina
      WHERE o.deleted='' AND $cond $AND
      GROUP BY id_osoba
    ");
    while ( $qu && ($u= pdo_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
      $idr= substr($u->_role,1);
      if ( $idr && !strpos(",$rodiny,",",$idr,") )
        $rodiny.= ",$idr";
      foreach (explode(',',$u->_ids_pobyt) as $idp) {
        $pobyt[$idp]->cleni[$u->id_osoba]= $u;
      }
      $spolu[$u->id_osoba]= $u->id_pobyt;
      // doplnění osobního umí - malým
      $pobyt[$u->id_pobyt]->x_umi= '';
      if ( isset($u->o_umi) && $u->o_umi ) {
        $pobyt[$u->id_pobyt]->x_umi.= strtolower($umi($u->o_umi));
      }
    }
    $osoby.= $cleni;
    # seznam rodinných příslušníků
    $qp= pdo_qry("
      SELECT id_pobyt,id_rodina,id_tvori,id_osoba,role,o_umi,o.narozeni
      FROM pobyt AS p
      JOIN tvori AS t ON t.id_rodina=p.i0_rodina
      JOIN osoba AS o USING(id_osoba)
      WHERE o.deleted='' AND $cond $AND
    ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
      $osoby.= ",{$p->id_osoba}";
      $idr= $p->id_rodina;
      if ( $idr && !strpos(",$rodiny,",",$idr,") )
        $rodiny.= ",$idr";
      if ( !isset($pobyt[$p->id_pobyt]->cleni[$p->id_osoba]) )
        $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]= (object)array();
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_tvori= $p->id_tvori;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_rodina= $idr;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->role= $p->role;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->o_umi= $p->o_umi;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->narozeni= $p->narozeni;
    }
    # atributy rodin
    $qr= pdo_qry("
      SELECT r.*,IF(g.stav=1,'ok','') AS _geo  
      FROM rodina AS r 
      LEFT JOIN rodina_geo AS g USING(id_rodina)
      WHERE deleted='' AND id_rodina IN (0$rodiny)");
    while ( $qr && ($r= pdo_fetch_object($qr)) ) {
      $r->datsvatba= sql_date_year($r->datsvatba);                  // svatba d.m.r
      $r->r_geo_ok= $r->_geo;                                       // ok|
      if ( $r->r_umi && $rodina_pobyt[$r->id_rodina] ) {
        // umí-li něco rodina a je na pobytu - velkým
        $pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi=
          strtoupper($umi($r->r_umi)).' '.$pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi;
      }
      $rodina[$r->id_rodina]= $r;
    }
    # atributy osob
    $qo= pdo_qry("
      SELECT o.*,IF(g.stav=1,'ok','') AS _geo 
      FROM osoba AS o 
      LEFT JOIN osoba_geo AS g USING(id_osoba)
      WHERE deleted='' AND id_osoba IN (0$osoby)");
    while ( $qo && ($o= pdo_fetch_object($qo)) ) {
      $o->access_web= (int)$o->access | ($o->web_zmena=='0000-00-00' ? 0 : 16);
      $osoba[$o->id_osoba]= $o;
    }
    # seznam rodin osob
    $css= $barvit
        ? "IF(r.access=1,':ezer_ys',IF(r.access=2,':ezer_fa',IF(r.access=3,':ezer_db','')))" : "''";
    $qor= pdo_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina,$css) SEPARATOR ','),'') AS _rody,
        SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen,
        SUM(IF(r.fotka='',0,1)) AS _rfotky
      FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
      WHERE o.deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= pdo_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array('_fotky'=>0);
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
      if ( !isset($osoba[$or->id_osoba]->_rfotky) )
        $osoba[$or->id_osoba]->_rfotky= 0;
      $osoba[$or->id_osoba]->_rfotky+= $or->_rfotky;
      foreach (explode(',',$or->_rody) as $rod) {
        list($role,$idr,$css)= explode(':',$rod);
        if ( !isset($rodina[$idr]) ) {
          # doplnění (potřebných) rodinných údajů pro kmenové rodiny
//                                                         display("{$or->id_osoba} - $kmen");
          $qr= pdo_qry("
            SELECT * -- id_rodina,nazev,ulice,obec,psc,stat,telefony,emaily
            FROM rodina AS r WHERE id_rodina=$idr");
          while ( $qr && ($r= pdo_fetch_object($qr)) ) {
            $rodina[$idr]= $r;
          }
        }
      }
    }
//                                                         display("rodiny:$rodiny");
//                                                         debug($rodina,$rodiny);
//                                                         debug($osoba,'osoby po _rody');
    # seznamy položek
    $fpob1= ucast2_flds("key_pobyt=id_pobyt,_empty=0,key_akce=id_akce,key_osoba,key_spolu,key_rodina=i0_rodina,"
           . "keys_rodina='',id_prihlaska,c_suma,platba=uhrada,potvrzeno,x_ms,xfunkce=funkce,funkce,xhnizdo=hnizdo,hnizdo,skupina,xstat,dluh,web_changes");
//           . "keys_rodina='',c_suma,platba,potvrzeno,x_ms,xfunkce=funkce,funkce,xhnizdo=hnizdo,hnizdo,skupina,dluh,web_changes");
    $fakce= ucast2_flds("dnu,datum_od");
    $frod=  ucast2_flds("fotka,r_access=access,r_access_web=access_web,r_spz=spz,"
          . "r_svatba=svatba,r_datsvatba=datsvatba,r_rozvod=rozvod,"
          . "r_ulice=ulice,r_psc=psc,r_obec=obec,r_stat=stat,r_geo_ok,"
          . "r_telefony=telefony,r_emaily=emaily,r_ms,r_umi,r_note=note");
    $fpob2= ucast2_flds("p_poznamka=poznamka,p_pracovni=pracovni,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_cel_bm,strava_cel_bl,strava_pol,strava_pol_bm,strava_pol_bl,"
          . "c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4,"
          . "v_nocleh=vratka1,v_strava=vratka2,v_program=vratka3,v_sleva=vratka4," /*datplatby,*/
          . "cstrava_cel,cstrava_cel_bm,cstrava_cel_bl,cstrava_pol,cstrava_pol_bm,cstrava_pol_bl,"
          . "svp,zpusobplat,naklad_d,poplatek_d,platba_d,potvrzeno_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text,x_umi");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,rc,narozeni,web_souhlas
    $fos=   ucast2_flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail"
          . ",email,gmail"
          . ",iniciace,firming,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
          . ",aktivita,note,_kmen,_geo");
    $fspo=  ucast2_flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id"
          . ",o_umi,prislusnost,skupinka");

    # 1. průchod - kompletace údajů mezi pobyty
    $skup= array();
    foreach ($pobyt as $idp=>$p) {
      if ( !$p->cleni || !count($p->cleni) ) continue;
      # seřazení členů podle přítomnosti, role, věku
      uasort($p->cleni,function($a,$b) {
        $arole= isset($a->role) ? $a->role : '';
        $brole= isset($b->role) ? $b->role : '';
        $wa= isset($a->id_spolu) && $a->id_spolu==0 ? 4 : ( $arole=='a' ? 1 : ( $arole=='b' ? 2 : 3));
        $wb= isset($b->id_spolu) && $b->id_spolu==0 ? 4 : ( $brole=='a' ? 1 : ( $brole=='b' ? 2 : 3));
        return $wa == $wb ? ($a->narozeni==$b->narozeni ? 0 : ($a->narozeni > $b->narozeni ? 1 : -1))
                          : ($wa==$wb ? 0 : ($wa > $wb ? 1 : -1));
      });
      # skupinky
      if ( $p->skupina ) {
        $skup[$p->skupina][]= $idp;
      }
      # osobní pečování
      foreach ($p->cleni as $ido=>$s) {
        if ( isset($s->id_spolu) && $s->id_spolu && ($idop= $s->pecovane) ) {
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
      $p_access= 0;
      $p_access_web= $p->web_zmena=='0000-00-00' ? 0 : 16;
      $idr= $p->i0_rodina ?: 0;
      $p->access= 5;
      $z= (object)array();
      $_ido01= $_ido02= $_ido1= $_ido2= 0;
      # agregace informací z členů pobytu
      $nazev= array();
      $_jmena= "";
      $clenu= 0;
      $fotek= 0;
      $o_fotek= $r_fotek= 0;
      $cleni= ""; $del= ""; $pecouni= 0;
      if ( $p->cleni && count($p->cleni) ) {
        foreach ($p->cleni as $ido=>$s) {
          $o= $osoba[$ido];
          if ( $p->funkce==99 ) $pecouni++;
          # první 2 členi v rodině
          if ( !$_ido01 )
            $_ido01= $ido;
          elseif ( !$_ido02 )
            $_ido02= $ido;
          if ( $s->id_spolu ) {
            # spočítání fotek
//             if ( $o->fotka || $o->_fotky ) $fotek++;
            if ( isset($o->rfotka) && $o->fotka ) $o_fotek++;
            if ( isset($o->_rfotky) && $o->_rfotky ) $r_fotek++;
            # spočítání účastníků kvůli platbě
            $clenu++;
            # první 2 členi na pobytu
            if ( !$_ido1 )
              $_ido1= $ido;
            elseif ( !$_ido2 )
              $_ido2= $ido;
            # výpočet jmen pobytu
            $_jmena.= str_replace(' ','-',trim($o->jmeno?:'?'))." ";
            if ( !$idr ) {
              # výpočet názvu pobyt
              $prijmeni= $o->prijmeni;
              if ( !in_array(trim($prijmeni),$nazev) ) $nazev[]= trim($prijmeni);
            }
            # barva
            if ( !isset($s->_barva) )
              $s->_barva= isset($s->id_tvori) && $s->id_tvori ? 1 : 2; // barva: 1=člen rodiny, 2=nečlen
            # barva nerodinného pobytu
            $p_access|= (int)$o->access;
            $p_access_web|= (int)$o->access_web;
            if (!$p->xstat && $o->stat!='CZ') $p->xstat= $o->prislusnost;
          }
          else {
            # neúčastník
            $s->_barva= 0;
          }
          # ==> .. duplicita členů
          $keys= $dup= '';
          if ( $duplicity ) {
            $ret= ucast2_chain_oso($ido,$idr);
            $dup= $s->_dup= $ret->dup;
            $keys= $s->_keys= $ret->keys;
          }
          # ==> .. seznam členů pro browse_fill
          $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni,$akce->datum_od) : '?'; // výpočet věku
          $jmeno= $p->funkce==99 ? "{$o->prijmeni} {$o->jmeno}" : $o->jmeno ;
          $sid_tvori= isset($s->id_tvori) ? $s->id_tvori : 0;
          $sid_rodina= isset($s->id_rodina) ? $s->id_rodina : 0;
          $srole= isset($s->role) ? $s->role : '';
          $cleni.= "$del$ido~$keys~$o->access~$o->access_web~$jmeno~$dup~$vek~$sid_tvori~$sid_rodina~$srole";
          $cleni.= '~'.rodcis($o->narozeni,$o->sex);
          $del= $delim;
          # ==> .. rodiny a kmenová rodina
          $rody= isset($o->_rody) ? explode(',',$o->_rody) : array();
          $r= "-:0:nerodina"; $kmen= '';
          foreach($rody as $rod) {
            list($role,$ir,$access)= explode(':',$rod);
            $naz= $rodina[$ir]->nazev;
            $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
//                                                 display("$o->jmeno/$role: $kmen ($naz,$ir)");
            $r.= ",$naz:$ir:$access";
          }
          $cleni.= "~$r";                                           // rody
          $id_kmen= isset($o->_kmen) && $o->_kmen ? $o->_kmen : 0;
          $o->_kmen= "$kmen/$id_kmen";
          $cleni.= "~" . sql_date_year($o->narozeni);                   // narozeniny d.m.r
          $cleni.= "~" . sql_date1($o->web_souhlas);                // souhlas d.m.r
          # doplnění textů z kmenové rodiny pro zobrazení rodinných adres (jako disabled)
//                                                 debug($o,"browse - o");
//                                                 debug($rodina[$id_kmen],"browse - kmen=$id_kmen");
          if ( !$o->adresa && $id_kmen ) {
            $o->ulice= "®".$rodina[$id_kmen]->ulice;
            $o->psc=   "®".$rodina[$id_kmen]->psc;
            $o->obec=  "®".$rodina[$id_kmen]->obec;
          }
          if ( !$o->kontakt && $id_kmen  ) {
            $o->email=   "®".$rodina[$id_kmen]->emaily;
            $o->telefon= "®".$rodina[$id_kmen]->telefony;
          }
          # informace z osoba
          foreach($fos as $f=>$filler) {
            $cleni.= "~{$o->$f}";
          }
          # informace ze spolu
          foreach($fspo as $f=>$filler) {
            $cleni.= isset($s->$f) ? "~{$s->$f}" : '~';
          }
        }
      }
  problem:
      # vynechání prázdného pobytu pečounů
      if ( $p->funkce==99 && !$pecouni ) {
        unset($pobyt[$idp]);
        continue;
      }
//                                                   debug($p->cleni,"členi");
//                                                   display($cleni);
      $_nazev= $p->funkce==99 ? "(pečouni)" : (
               $idr ? $rodina[$idr]->nazev : (
               $nazev ? implode(' ',$nazev) : '(pobyt bez členů)'));
      $_jmena= $p->funkce==99 ? "(celkem $pecouni)" : $_jmena;
      # zjištění dluhu
      $platba1234= $p->platba1 + $p->platba2 + $p->platba3 + $p->platba4;
      $p->c_suma= $platba1234 + $p->poplatek_d;
      // pokud není cena předepsána individuálně, podívej se na nastavení akce
      if ($p->c_suma==0 && $akce->ma_cenu)
        $p->c_suma= $clenu * $akce->cena;
      $p->dluh= $p->funkce==99 ? 0 : (
                $akce->soubeh==1 && $akce->ma_cenik
        ? ( $p->c_suma == 0 ? 2 : ( $p->c_suma > $p->uhrada ? 1 : 0 ) )
        // není to pečoun a není to souběžná akce
        : ( $akce->ma_cenik
          ? ( $platba1234 == 0 ? 2 : ( $platba1234 > $p->uhrada ? 1 : 0) )
          : ( $akce->ma_cenu 
            ? ( ($platba1234 ?: $clenu*$akce->cena) > $p->uhrada ? 1 : 0) : 0 )
          ));
      // ezer_cms3: web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
      // prihlaska: web_changes= 1/2 pro INS/UPD pobyt+spolu | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina,tvori
      $p->web_changes= $p->web_changes&4 ? 2 : ($p->web_changes ? 1 : 0);
//                                                         if ($idp==15826) { debug($akce);debug($p,"platba1234=$platba1234"); }
      # pobyt I
      foreach($fpob1 as $fz=>$fp) { $z->$fz= isset($p->$fp) ? $p->$fp : ''; }
      # akce
      foreach($fakce as $fz=>$fp) { $z->$fz= isset($akce->$fp) ? $akce->$fp : ''; }
      $z->_nazev= $_nazev;
      $z->_jmena= $_jmena;
      # ==> .. dokumenty
      #        ucast2 musí měnit složku aplikace
      $z->_docs= '';
      if ( $dokumenty ) $z->_docs.= drop_find("pobyt/","(.*)_$idp",'H:') ? 'd' : '';
      # ==> .. fotky
      if ( $r_fotek ) { $z->_docs.= 'F'; }
      if ( $o_fotek ) { $z->_docs.= 'f'; }
      # ==> .. duplicity
      $z->_dupl= 0;
      if ( $duplicity ) {
        if ( $r_fotek || $o_fotek ) { $z->_docs.= ' '; }
        $del= "|";
        if ( $p->funkce==99 ) {
          $_dups= '';
          $_keys= '';
        }
        else {
          $ret= ucast2_chain_rod($idr);
          $_dups= $idr ? $ret->dup : '';
          $_keys= $ret->keys;
        }
        if ( $p->cleni ) foreach ($p->cleni as $ido=>$s) {
          if ( $s->_dup ) {
            $_dups.= $s->_dup;
            $_keys.= "$del$ido,{$s->_keys}";
            $del= ";";
          }
        }
        $z->_docs.= count_chars($_dups,3);
        $z->keys_rodina= $_keys;
        $z->_dupl= $_keys ? 1 : 0;
      }
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= isset($rodina[$idr]->$fr) ? $rodina[$idr]->$fr : ''; }
      $z->r_access= intval($z->r_access);
      # ... oprava obarvení
      if ( $p_access )
        $z->r_access|= (int)$p_access;
      $z->r_access_web= !$idr ? 0
          : (int)$rodina[$idr]->access | ($rodina[$idr]->web_zmena=='0000-00-00' ? 0 : 16);
      $z->p_access_web= (int)$p_access_web | (int)$z->r_access_web;
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->vratka= $p->vratka1 + $p->vratka2 + $p->vratka3 + $p->vratka4;
      $z->key_spolu= 0;
      $z->ido1= $_ido1 ?: $_ido01;
      $z->ido2= $_ido2; // ?: $_ido02;
//                                                 display("$idr {$z->ido1} + {$z->ido2}");
//      $z->datplatby= sql_date1($z->datplatby);                   // d.m.r
//      $z->datplatby_d= sql_date1($z->datplatby_d);               // d.m.r
      # ok
      $zz[$idp]= $z;
      continue;
//     p_end: // varianta pro prázdný pobyt - definování položky _empty:1
//       $zz[$idp]= (object)array('key_pobyt'=>$idp,'_empty'=>1);
    }
    # 3. průchod - kompletace údajů mezi pobyty
    foreach ($pobyt as $idp=>$p) {
      # doplnění skupinek
      $s= $del= '';
      if ( ($sk= $p->skupina) && $skup[$sk]) {
        $skupinka= array();
        foreach($skup[$sk] as $ip) {
          $skupinka[]= array($ip,$zz[$ip]->_nazev,$zz[$ip]->funkce==1?1:0);
        }
        usort($skupinka,function($a,$b){return $b[2]-$a[2];});
        foreach($skupinka as $par) {
          $s.= "$del$par[0]~$par[1]";
          $del= $delim;
        }
      }
      if ( !isset($zz[$idp]) ) $zz[$idp]= (object)array();
      $zz[$idp]->skup= $s;
    }
    # případný výběr - zjednodušeno na show=[*,vzor]
    if ( isset($x->show) ) foreach ( $x->show as $fld => $show) {
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
    # ==> .. řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('skupina','x_ms','vratka','c_suma','platba'));
      if ( $numeric ) {
//                                         display("usort $test_clmn $test_asc/numeric");
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
            $a0= mb_substr($a->$test_clmn,0,1);
            $b0= mb_substr($b->$test_clmn,0,1);
            if ( $a0=='(' ) {
              $c= -$test_asc;
            }
            elseif ( $b0=='(' ) {
              $c= $test_asc;
            }
            else {
              $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
            }
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
//                                                 debug($pobyt[60350],'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba[7039],'osoba');
//                                                 debug($y->values);
  return $y;
}
# dekódování seznamu položek na pole ...x,y=z... na [...x=>x,y=>z...]
function ucast2_flds($fstr) {
  $fp= array();
  foreach(explode(',',$fstr) as $fzp) {
    list($fz,$f)= explode('=',$fzp.'=');
    $fp[$fz]= $f ?: $fz ;
  }
  return $fp;
}
# ========================================================================================> . platby
# záložka Platba za akci
# ------------------------------------------------------------------------------------- akce2 uhrada
//# transformace osoba.platba* --> uhrada
//function akce2_uhrada() {  trace();
//  query("TRUNCATE TABLE uhrada");
//  query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za) 
//  /*ok*/ SELECT id_pobyt,0,platba,datplatby,zpusobplat,IF(potvrzeno=1,3,IF(avizo=1,1,2)),0
//      FROM pobyt WHERE platba!=0");
//  query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za) 
//  /*ok*/ SELECT id_pobyt,IF(platba=0,0,1),platba_d,datplatby_d,zpusobplat_d,IF(potvrzeno=1,3,2),1
//      FROM pobyt WHERE platba_d!=0");
//}
# -------------------------------------------------------------------------- akce2 platba_prispevek1
# členské příspěvky - zjištění zda jsou dospělí co jsou na pobytu členy a mají-li zaplaceno
function akce2_platba_prispevek1($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'nejsou členy','platit'=>0);
  // jsou členy?
  $cleni= 0;
  $qp= "SELECT COUNT(*) AS _jsou, GROUP_CONCAT(jmeno) AS _jmena
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON o.id_osoba=s.id_osoba
        LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba AND d.deleted=''
        WHERE id_pobyt=$id_pobyt AND ukon='c'
        GROUP BY id_pobyt ";
  $rp= pdo_qry($qp);
  if ( $rp && $p= pdo_fetch_object($rp) ) {
    $cleni= $p->_jsou;
    $ret->msg= $p->_jmena." jsou členy ";
  }
  if ( $cleni ) {
    $qp= "SELECT COUNT(*) AS _maji, MAX(dat_do) AS _do, GROUP_CONCAT(jmeno) AS _jmena
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba AND d.deleted=''
          WHERE id_pobyt=$id_pobyt AND ukon='p' AND YEAR(dat_do)>=YEAR(NOW()) 
          GROUP BY id_pobyt";
    $rp= pdo_qry($qp);
    if ( $rp && $p= pdo_fetch_object($rp)) {
      $jmena= $p->_jmena;
      if ( $p->_maji && $p->_maji==$cleni ) {
        $ret->platit= 0;
        $ret->msg.= "a mají zaplaceno do ".sql_date1($p->_do);
      }
      elseif ( $p->_maji ) { // < cleni
        $kolik= 
        $ret->platit= 1;
        $ret->msg.= "ale jen $jmena má zaplaceno do ".sql_date1($p->_do)
            .". Doplatí to nyní na akci do pokladny?";
      }
      else {
        $ret->platit= 1;
        $ret->msg.= "a nemají letos zaplaceno, zaplatí nyní na akci do pokladny?";
      }
    }
  }
  return $ret;
}
# -------------------------------------------------------------------------- akce2 platba_prispevek2
# členské příspěvky vložení platby do dar
function akce2_platba_prispevek2($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'');
  $osoby= array();
  $prispevek= 100;
  $nazev= $rok= '';
  $values= $del= $jmena= '';
  $celkem= 0;
  $qp= "SELECT IFNULL(dp.castka,0) AS _ma,
          o.id_osoba,o.jmeno,a.datum_do,a.nazev,YEAR(a.datum_do) AS _rok,a.access
        FROM pobyt AS p
        JOIN akce AS a ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        LEFT JOIN dar AS dc 
          ON dc.id_osoba=o.id_osoba AND dc.deleted='' AND dc.ukon='c'
        LEFT JOIN dar AS dp 
          ON dp.id_osoba=o.id_osoba AND dp.deleted='' AND dp.ukon='p' 
            AND YEAR(dp.dat_do)>=YEAR(NOW()) 
        WHERE id_pobyt=$id_pobyt ";
  $rp= pdo_qry($qp);
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    if ($p->_ma) continue;
    $osoba= $p->id_osoba;
    $rok= $p->_rok;
    $datum= $p->datum_do;
    $nazev= "$rok - {$p->nazev}";
    $values.= "$del({$p->access},$osoba,'p',$prispevek,'$datum','$rok-12-31','$nazev')";
    $jmena.= "$del{$p->jmeno}";
    $del= ', ';
    $celkem+= $prispevek;
  }
//                                                         display($values);
  $qi= "INSERT dar (access,id_osoba,ukon,castka,dat_od,dat_do,note) VALUES $values";
  $ri= pdo_qry($qi);
  display("SQL: $qi");
  // odpověď
  $ret->msg= "Za člena $jmena vlož do pokladny $celkem,- Kč";
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 uhrady_load
# načtení úhrad za pobyt
function akce2_uhrady_load($id_pobyt) { 
  $ret= (object)array('pocet'=>0,'seznam'=>array());
  $rp= pdo_qry("SELECT u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za FROM uhrada
          WHERE id_pobyt=$id_pobyt ORDER BY u_poradi");
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    $ret->pocet++;
//    $p->u_poradi= $p->u_poradi+1; unset($p->u_poradi);
    $p->u_datum= sql_date1($p->u_datum);
    $ret->seznam[]= $p;
  }
//  debug($ret,"akce2_uhrady_load($id_pobyt)");
  return $ret;
}
# --------------------------------------------------------------------------------- akce2 uhrady_new
# vrátí u_poradi pro novou úhradu
function akce2_uhrady_new($id_pobyt) { 
  $u_poradi= select('MAX(u_poradi)','uhrada',"id_pobyt=$id_pobyt");
  return ($u_poradi ?: 0) + 1;
}
# -------------------------------------------------------------------------------- akce2 uhrady_save
# uložení změn úhrad za pobyt
function akce2_uhrady_save($id_pobyt,$uhrady) { 
//  debug($uhrady,"akce2_uhrady_save($id_pobyt)");
  foreach ($uhrady as $new) {
    $new->u_datum= sql_date1($new->u_datum,1);
    $old= select_object('u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za','uhrada',
        "id_pobyt=$id_pobyt AND u_poradi=$new->u_poradi");
    if ($old) {
      foreach ($new as $fld=>$val) {
//        if ($fld=='u_index') {
//          $fld= 'u_poradi';
//          $val--;
//        }
        if ($old->$fld != $val) {
          query("UPDATE uhrada SET $fld='$val' WHERE id_pobyt=$id_pobyt AND u_poradi=$new->u_poradi");
        }
      }
    }
    else {
      query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,
          u_stav,u_za) 
        VALUES ($id_pobyt,$new->u_poradi,'$new->u_castka','$new->u_datum',$new->u_zpusob,
          $new->u_stav,$new->u_za)");
    }
  }
}
# ====================================================================================> . autoselect
# ---------------------------------------------------------------------------------- akce2 auto_deti
# SELECT autocomplete - výběr z dětí na akci=par->akce
function akce2_auto_deti($patt,$par) {  #trace();
//                                                                 debug($par,$patt);
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // děti na akci
  $qry= "SELECT prijmeni, jmeno, s.id_osoba AS _key
         FROM osoba AS o
         JOIN spolu AS s USING(id_osoba)
         JOIN pobyt AS p USING(id_pobyt)
         LEFT JOIN tvori AS t ON s.id_osoba=t.id_osoba AND t.id_rodina=p.i0_rodina
         WHERE o.deleted='' AND prijmeni LIKE '$patt%' AND role='d' AND id_akce='{$par->akce}'
           AND s_role=3
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
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
# -------------------------------------------------------------------------------- ucast2_auto_jmeno
# test autocomplete
function ucast2_auto_jmeno($patt,$par) {  trace();
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadejte jméno";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT id_jmena AS _key,jmeno AS _value
           FROM _jmena
           WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... nic nezačíná $patt";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
  return $a;
}
# ------------------------------------------------------------------------------- ucast2_auto_jmeno2
# test autocomplete - hledá jména ve všech organizacích, použité v příjmení
function ucast2_auto_jmeno2($patt,$par) {  trace();
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( $par->prefix ) {
    $patt= "{$par->prefix}$patt";
  }
  // zpracování vzoru
  $qry= "SELECT TRIM(jmeno) AS _value
         FROM osoba
         WHERE prijmeni='{$par->prijmeni}' AND TRIM(jmeno)!='' AND jmeno LIKE '$patt%'
         GROUP BY _value
         ORDER BY _value LIMIT $limit
         ";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    ++$n;
    if ( $n==$limit ) break;
    $a->{$n}= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a->{0}= "... nic nezačíná $patt";
  elseif ( $n==$limit )
    $a->{999999}= "... a další";
  return $a;
}
# ----------------------------------------------------------------------------- ucast2_auto_prijmeni
# SELECT autocomplete - výběr ze jmen rodin
function ucast2_auto_prijmeni($patt,$par) {  #trace();
  $a= (object)array();
  $limit= 11;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadávejte jméno osoby";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT prijmeni AS _value, id_osoba AS _key
           FROM osoba
           WHERE deleted='' AND prijmeni LIKE '$patt%'
           GROUP BY prijmeni
           ORDER BY prijmeni
           LIMIT $limit";
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $key= $t->_key;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... žádná osoba nezačíná '$patt'";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
//                                                                 debug($a,$patt);
  return $a;
}
# ------------------------------------------------------------------------------- ucast2_auto_rodiny
# SELECT autocomplete - výběr ze jmen rodin
function ucast2_auto_rodiny($patt,$par) {  #trace();
  $a= (object)array();
  $limit= 11;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadávejte jméno rodiny";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT nazev AS _value, id_rodina AS _key
           FROM rodina
           WHERE deleted='' AND nazev LIKE '$patt%'
           GROUP BY nazev
           ORDER BY nazev
           LIMIT $limit";
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $key= $t->_key;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... žádná rodina nezačíná '$patt'";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
//                                                                 debug($a,$patt);
  return $a;
}
# -------------------------------------------------------------------------------- akce2 auto_jmena1
# SELECT autocomplete - výběr z dospělých osob, pokud je par.deti=1 i z deti
function akce2_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // osoby
  $AND= $par->deti ? '' 
      : "AND (narozeni='0000-00-00' OR IF(MONTH(narozeni),DATEDIFF(NOW(),narozeni)/365.2425,YEAR(NOW())-YEAR(narozeni))>15)";
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         LEFT JOIN tvori USING(id_osoba)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
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
# ------------------------------------------------------------------------------- akce2 auto_jmena1L
# formátování autocomplete
function akce2_auto_jmena1L ($id_osoba) {  #trace();
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
  $res= pdo_qry($qry);
  if ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= "$p->prijmeni $p->jmeno / $p->rok, $p->obec, $p->ulice, $p->email, $p->telefon";
    $osoba[]= (object)array('nazev'=>$nazev,'id'=>$id_osoba,'role'=>$p->role);
  }
//                                                                 debug($osoba,$id_akce);
  return $osoba;
}
# -------------------------------------------------------------------------------- akce2 auto_jmena3
# SELECT autocomplete - výběr z pečounů
# $par->cond může obsahovat dodatečnou podmínku např. 'funkce=99' pro zúžení na pečouny
function akce2_auto_jmena3($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  $AND= $par->cond ? "AND {$par->cond}" : '';
  // páry
  $qry= "SELECT prijmeni, jmeno, osoba.id_osoba AS _key
         FROM osoba
         JOIN spolu USING(id_osoba)
         JOIN pobyt USING(id_pobyt)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         GROUP BY id_osoba
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ------------------------------------------------------------------------------- akce2 auto_jmena3L
# formátování autocomplete
function akce2_auto_jmena3L($id_osoba) {  #trace();
  $pecouni= array();
  // páry
  $qry= "SELECT id_osoba, prijmeni, jmeno, obec, email, telefon, YEAR(narozeni) AS rok
         FROM osoba AS o
         WHERE id_osoba='$id_osoba' ";
  $res= pdo_qry($qry);
  while ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}, {$p->obec}, {$p->email}, {$p->telefon}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# --------------------------------------------------------------------------------- akce2 auto_akceL
# formátování autocomplete
function akce2_auto_akceL($id_akce) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT
           IFNULL(r.nazev,o.prijmeni) as _nazev,r.id_rodina,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.id_osoba,'') SEPARATOR '') AS _zena_id,
           r.obec
         FROM pobyt AS p
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.role!='d'
         LEFT JOIN rodina AS r USING(id_rodina)
         LEFT JOIN tvori AS tx ON r.id_rodina=tx.id_rodina
         LEFT JOIN osoba AS ox ON tx.id_osoba=ox.id_osoba
         WHERE id_akce=$id_akce
	 GROUP BY p.id_pobyt ORDER BY _nazev";
  $res= pdo_qry($qry);
  while ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $pary[]= (object)array(
      'nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id,'rod'=>$p->id_rodina);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ---------------------------------------------------------------------------------- akce2 auto_pece
# SELECT autocomplete - výběr z akcí na kterých byli pečouni
function akce2_auto_pece($patt) {  #trace();
  $a= array();
  $limit= 20;
  $patt= substr($patt,-7,2)==' (' && substr($patt,-1)==')' ? substr($patt,0,-7) : $patt;
  $n= 0;
  // výběr akce
  $qry= "SELECT id_duakce AS _key,concat(nazev,' (',YEAR(datum_od),')') AS _value
         FROM akce
         JOIN pobyt ON akce.id_duakce=pobyt.id_akce
         WHERE nazev LIKE '$patt%' AND funkce=99
         ORDER BY datum_od DESC LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádná jméno akce nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ======================================================================================> . SKUPINKY
# --------------------------------------------------------------------------------- akce2 skup_check
# zjištění konzistence skupinek podle příjmení VPS/PPS
function akce2_skup_check($akce) {
  $ret= akce2_skup_get($akce,1,$err);
  return $ret->msg;
}
# ------------------------------------------------------------------ akce2 skup_get
# zjištění skupinek podle příjmení VPS/PPS
# pokud je par.mark=LK vrátí se skupinky z letního kurzu s informací, jestli jsou na této akci
# pokud je par.mark=PO vrátí se skupinky z předchozí obnovy s informací, jestli jsou na této akci
function akce2_skup_get($akce,$kontrola,&$err,$par=null) { trace();
//                                                         debug($par,"akce2_skup_get");
  global $VPS, $tisk_hnizdo;
  $ret= (object)array('lk'=>0);
  $msg= array();
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $skupiny= array();
  // přechod na LK pro par->mark=LK ... a pokud jde o obnovu
  if ( isset($par->mark) && ($par->mark=='LK' || $par->mark=='PO') ) {
    list($access,$druh,$kdy)= select('access,druh,datum_od','akce',"id_duakce=$akce");
    if ( $druh==2 /*MS obnova*/ ) {
      $pred_druh= $par->mark=='LK' ? 1 : 2;
      $akce= select('id_duakce','akce',
        "access=$access AND druh=$pred_druh AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
      $ret->lk= $akce;
    }
    else { $msg[]= "tento výpis má smysl jen pro obnovy MS"; $err= -1; $kontrola= 1; goto end; }
  }
  $celkem= select('count(*)','pobyt',"id_akce=$akce AND funkce IN (0,1,2) $jen_hnizdo AND skupina!=-1");
  $n= 0;
  $err= 0;
  $order= $all= array();
  $qry= "
      SELECT skupina,
        SUM(IF(funkce=2,1,0)) as _n_svps,
        SUM(IF(funkce=1,1,0)) as _n_vps,
        GROUP_CONCAT(DISTINCT IF(funkce=2,id_pobyt,'') SEPARATOR '') as _svps,
        GROUP_CONCAT(DISTINCT IF(funkce=1,id_pobyt,'') SEPARATOR '') as _vps,
        GROUP_CONCAT(DISTINCT id_pobyt) as _skupina
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      WHERE p.id_akce=$akce $jen_hnizdo AND skupina>0
      GROUP BY skupina ";
  $res= pdo_qry($qry);
  while ( $res && ($s= pdo_fetch_object($res)) ) {
    if ( $s->_n_svps==1 || $s->_n_vps==1 ) {
      $skupina= array();
      if ( $par && $par->verze=='DS' ) {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(o.id_osoba) as ids_osoba,
            GROUP_CONCAT(o.id_osoba) as id_osoba_m,
            GROUP_CONCAT(CONCAT(o.prijmeni,' ',o.jmeno,'')) AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina}) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      elseif ( $par && $par->verze=='MS' ) {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,i0_rodina,
            GROUP_CONCAT(o.id_osoba) as ids_osoba,
            GROUP_CONCAT(o.id_osoba) as id_osoba_m,
            CONCAT(nazev,' ',GROUP_CONCAT(o.jmeno ORDER BY t.role SEPARATOR ' a ')) AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina}) AND funkce IN (0,1,2,5,6) AND t.role IN ('a','b') 
            $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      else {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(DISTINCT IF(t.role IN ('a','b'),o.id_osoba,'')) as ids_osoba,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            CASE WHEN pouze=0 THEN
              CONCAT(nazev,' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),' a ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=1 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=2 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            END AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina}) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      $resu= pdo_qry($qryu);
      while ( $resu && ($u= pdo_fetch_object($resu)) ) {
        $mark= '';
        if ( $par && $par->mark=='novic' ) {
          // minulé účasti
          $ids= $u->ids_osoba;
          $rqry= "SELECT count(*) as _pocet
                  FROM akce AS a
                  JOIN pobyt AS p ON a.id_duakce=p.id_akce
                  JOIN spolu AS s USING(id_pobyt)
                  WHERE a.druh=1 AND s.id_osoba IN ($ids) AND p.id_akce!=$akce $jen_hnizdo";
          $rres= pdo_qry($rqry);
          if ( $rres && ($r= pdo_fetch_object($rres)) ) {
            $mark= $r->_pocet;
          }
          $mark= $mark==0 ? '* ' : '';
        }
        $u->_nazev= "$mark {$u->_nazev}";
        $skupina[$u->id_pobyt]= $u;
        $n++;
      }
      $vps= $s->_svps ? $s->_svps : $s->_vps;
      $skupiny[$vps]= $skupina;
      $all[]= $vps;
    }
    elseif ( $s->_vps || $s->_vps ) {
      $msg[]= "skupinka {$s->skupina} má nejednoznačnou $VPS";
      $err+= 2;
    }
    else {
      $msg[]= "skupinka {$s->skupina} nemá $VPS";
      $err+= 4;
    }
  }
//  debug($all,'ALL');
  // řazení - v PHP nelze udělat
  if ( count($all) ) {
    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
            FROM pobyt AS p
            JOIN rodina AS r ON id_rodina=i0_rodina
            WHERE id_pobyt IN (".implode(',',$all).") $jen_hnizdo ";
//    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
//            FROM pobyt AS p
//            JOIN spolu AS s USING(id_pobyt)
//            JOIN osoba AS o ON s.id_osoba=o.id_osoba
//            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
//            LEFT JOIN rodina AS r USING(id_rodina)
//            WHERE id_pobyt IN (".implode(',',$all).") $jen_hnizdo ";
    $reso= pdo_qry($qryo);
    while ( $reso && ($o= pdo_fetch_object($reso)) ) {
      foreach (explode(',',$o->_o) as $pair) {
        list($id,$name)= explode('|',$pair);
        $order[$id]= $name;
      }
    }
  }
//                                                         debug($order,"order");
  $skup= array();
  foreach($order as $i=>$nam) {
    $skup[$i]= $skupiny[$i];
  }
//                                                         debug($skup,"skupiny");
  // redakce chyb
  if ( $celkem>$n ) {
    $msg[]= ($celkem-$n)." účastníků není zařazeno do skupinek";
    $err+= 1;
  }
  elseif ( $celkem<$n ) {
    $msg[]= ($n-$celkem)." je zařazeno do skupinek navíc (třeba hospodáři?)";
    $err+= 1;
  }
  if ( count($msg) && !$kontrola )
    fce_warning(implode(",<br>",$msg));
  elseif ( !count($msg) && $kontrola )
    $msg[]= "Vše je ok";
  // konec
end:
  $ret->skupiny= $skup;
  $ret->msg= implode(",<br>",$msg);
  return $ret;
}

# --------------------------------------------------------------------------------- akce2 skup_renum
# přečíslování skupinek podle příjmení VPS/PPS
function akce2_skup_renum($akce) {
  $err= 0;
  $msg= '';
  $par= (object)array('verze'=>'MS');
//  $par= (object)array('verze'=>'DS');
  $ret= akce2_skup_get($akce,0,$err,$par);
//  debug($ret); goto end;
  $skupiny= $ret->skupiny;
  if ( $err>1 ) {
    $msg= "skupinky nejsou dobře navrženy - ještě je nelze přečíslovat";
  }
  else {
    $n= 1;
    foreach($skupiny as $ivps=>$skupina) {
      $cleni= implode(',',array_keys($skupina));
      $qryu= "UPDATE pobyt SET skupina=$n WHERE id_pobyt IN ($cleni) ";
      $resu= pdo_qry($qryu);
//                                                         display("$n: $qryu");
      $n++;
    }
    $msg= "bylo přečíslováno $n skupinek";
  }
end:
  return $msg;
}
# =======================================================================================> . pomocné
//# ---------------------------------------------------------------------------- akce2 rodina_z_pobytu
//# vrátí rodiny dané osoby ve formátu pro select (název:id_rodina;...)
//function ucast2_rodina_z_pobytu($idp) {
//  $idr= 0; // název rodiny podle nejstaršího člena pobytu
//  $res= pdo_qry("SELECT id_osoba, TRIM(prijmeni), sex, ulice, psc, obec,
//          ROUND(IF(MONTH(narozeni),
//            DATEDIFF(datum_od,narozeni)/365.2425,YEAR(datum_od)-YEAR(narozeni)),1) AS _vek,
//          a.access
//         FROM osoba 
//           JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) 
//           JOIN akce AS a ON id_akce=id_duakce 
//         WHERE id_pobyt=$idp 
//         ORDER BY narozeni");
//  while ( $res && (list($ido,$prijmeni,$sex,$ulice,$psc,$obec,$vek,$access)= pdo_fetch_array($res)) ) {
//    if (!$idr) { 
//      // vytvoř rodinu podle nejstaršího
//      $nazev= preg_replace('~ová$~','',$prijmeni).'ovi';
//      $idr= ezer_qry("INSERT",'rodina',0,array(
//        (object)array('fld'=>'nazev', 'op'=>'i','val'=>$nazev),
//        (object)array('fld'=>'access','op'=>'i','val'=>$access),
//        (object)array('fld'=>'ulice', 'op'=>'i','val'=>$ulice),
//        (object)array('fld'=>'psc',   'op'=>'i','val'=>$psc),
//        (object)array('fld'=>'obec',  'op'=>'i','val'=>$obec)
//      ));
//    }
//    // a přidávej členy rodiny
//    $role= $vek<18 ? 'd' : ($sex==1 ? 'a' : 'b');
//    ezer_qry("INSERT",'tvori',0,array(
//      (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
//      (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
//      (object)array('fld'=>'role',     'op'=>'i','val'=>$role)
//    ));
//  }
//  return $idr;
//}
# ------------------------------------------------------------------------------- akce2 osoba_rodiny
# vrátí rodiny dané osoby ve formátu pro select (název:id_rodina;...)
function akce2_osoba_rodiny($id_osoba) {
  $rodiny= select1("GROUP_CONCAT(CONCAT(nazev,':',id_rodina) SEPARATOR ',')",
    "rodina JOIN tvori USING(id_rodina)","id_osoba=$id_osoba");
  $rodiny= "-:0".($rodiny ? ',' : '').$rodiny;
                                                display("akce_osoba_rodiny($id_osoba)=$rodiny");
  return $rodiny;
}
# ------------------------------------------------------------------------------ akce2 pobyt_rodinny
# definuje pobyt jako rodinný
function akce2_pobyt_rodinny($id_pobyt,$id_rodina) { trace();
  query("UPDATE pobyt SET i0_rodina=$id_rodina WHERE id_pobyt=$id_pobyt");
  return 1;
}
# ---------------------------------------------------------------------------------- akce2 save_role
# zapíše roli - je to netypická číselníková položka definovaná jako VARCHAR(1)
function akce2_save_role($id_tvori,$role) { //trace();
  return pdo_qry("UPDATE tvori SET role='$role' WHERE id_tvori=$id_tvori");
}
# ------------------------------------------------------------------------------- akce2 save_pfunkce
# ASK
# zapíše spolu.pfunkce - funkce pro hlavouny
function akce2_save_pfunkce($ids,$pfunkce) { //trace();
  ezer_qry("UPDATE","spolu",$ids,array(
    (object)array('fld'=>'pfunkce', 'op'=>'u','val'=>$pfunkce)
  ));
  return 1;
}
# ------------------------------------------------------------------------------------ akce2 osoba2x
# ASK volané z formuláře _osoba2x při onchange.adresa a onchange.kontakt
# v ret vrací o_kontakt, r_kontakt, o_adresa, r_adresa
# pokud je id_osoba=0 tzn. jde o novou osobu, vrací se v r_* údaje pro id_rodina
function akce2_osoba2x($id_osoba,$id_rodina=0) { trace();
  $rets= "o_kontakt,r_kontakt,o_adresa,r_adresa";
  $adresa= "ulice,psc,obec,stat,noadresa";
  $kontakt= "telefon,email,nomail";         // rodina s -y na konci
  $kontakty= "telefony,emaily,nomaily";
  $ret= (object)array();
  if ( $id_osoba ) {
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
//                                                         debug($k,"kmen");
//                                                         debug($r,"rodina");
//                                                         debug($o,"osoba ".(empty($o)?'e':'f'));
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
  }
  elseif ( $id_rodina ) {
    $o= (object)array();
    $r= sql_query("SELECT $adresa,$kontakty FROM rodina WHERE id_rodina='$id_rodina'");
  }
  foreach(explode(',',$rets) as $f) {
    $ret->$f= (object)array();
  }
  foreach(explode(',',$adresa) as $f) {
    $ret->o_adresa->$f= isset($o->$f) ? $o->$f : '';
    $ret->r_adresa->$f= empty($r) ? '' : ($f=='noadresa'||$f=='stat'?'':'®').$r->$f;
  }
  foreach(explode(',',$kontakt) as $f) { $fy= $f.'y';
    $ret->o_kontakt->$f= isset($o->$f) ? $o->$f : '';
    $ret->r_kontakt->$f= empty($r) ? '' : ($f=='nomail'?'':'®').$r->$fy;
  }
//                                                         debug($ret,"akce2__osoba2x");
  return $ret;
}
# ------------------------------------------------------------------------------------ akce2 ido2idp
# ASK
# získání pobytu účastníka na akci
function akce2_ido2idp($id_osoba,$id_akce) {
  $idp= select("id_pobyt","spolu JOIN pobyt USING (id_pobyt)",
    "spolu.id_osoba=$id_osoba AND id_akce=$id_akce");
  return $idp;
}
# ----------------------------------------------------------------------------- ucast2_rodina_access
# ASK přidání access členům rodiny
function ucast2_rodina_access($idr,$access) {
  $qo= pdo_qry("SELECT id_osoba,o.access,r.access AS r_access FROM rodina AS r
                  LEFT JOIN tvori USING (id_rodina) LEFT JOIN osoba AS o USING (id_osoba)
                  WHERE id_rodina=$idr");
  while ( $qo && ($o= pdo_fetch_object($qo)) ) {
    $r_access= $o->r_access;
    # úprava access členů
    if ( $access!=$o->access) {
      ezer_qry("UPDATE",'osoba',$o->id_osoba,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o->access)
      ));
    }
  }
  # úprava access rodiny
  if ( $access!=$o->access) {
    ezer_qry("UPDATE",'rodina',$idr,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
    ));
  }
  return 1;
}
# ----------------------------------------------------------------------------- ucast2_pridej_rodinu
# ASK přidání rodinného pobytu do akce (pokud ještě nebyla rodina přidána)
function ucast2_pridej_rodinu($id_akce,$id_rodina,$hnizdo=0) { trace();
  $ret= (object)array('idp'=>0,'msg'=>'');
  // kontrola definice rodiny
  if ( !$id_rodina ) { $ret->msg= "Nelze přidat pobyt neznámé rodiny"; goto end; }
  // kontrola nepřítomnosti
  $jsou= select1('COUNT(*)','pobyt',"id_akce=$id_akce AND i0_rodina='$id_rodina'");
  if ( $jsou ) { // už jsou na akci
    $ret->msg= "... rodina již je přihlášena na akci";
  }
  else {
    // vložení nového pobytu
    $rod= $pouze==0 && $info->rod ? $info->rod : 0;
    // přidej k pobytu
    $chng= array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$id_akce),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$id_rodina)
    );
    if ( $hnizdo )
      $chng[]= (object)array('fld'=>'hnizdo', 'op'=>'i','val'=>$hnizdo);
    $ret->idp= ezer_qry("INSERT",'pobyt',0,$chng);
  }
end:
//                                                 debug($ret,'ucast2_pridej_rodinu');
  return $ret;
}
# ------------------------------------------------------------------------------ ucast2_pridej_osobu
# ASK přidání osoby k pobytu, případně k rodině a upraví access
#   je-li zadáno access, opraví je v OSOBA
#   není-li zadán pobyt, vytvoří nový, přidá SPOLU - hlídá duplicity
#   je-li zadána rodina, přidá TVORI s rolí - hlídá duplicity
# spolupracuje s číselníky: ms_akce_s_role, ys_akce_dite_kat a fa_akce_dite_kat
#   podle stáří resp. role odhadne hodnotu SPOLU.s_role a SPOLU.dite_kat
#  vrací
#   ret.spolu,tvori - klíče vytvořených záznamů stejnojmenných tabulek nebo 0
#   ret.pobyt - parametr nebo nové vytvořený pobyt
function ucast2_pridej_osobu($ido,$access,$ida,$idp,$idr=0,$role=0,$hnizdo=0) { trace();
  $ret= (object)array('pobyt'=>$idp,'spolu'=>0,'tvori'=>0,'msg'=>'');
  list($narozeni,$old_access)= select("narozeni,access","osoba","id_osoba=$ido");
  # případné vytvoření pobytu
  if ( !$idp ) {
    $chng= array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$ida),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$idr)
    );
    if ( $hnizdo )
      $chng[]= (object)array('fld'=>'hnizdo', 'op'=>'i','val'=>$hnizdo);
    $idp= $ret->pobyt= ezer_qry("INSERT",'pobyt',0,$chng);
  }
  # přidání k pobytu
  list($je,$funkce)=
    select("COUNT(*),funkce","pobyt JOIN spolu USING(id_pobyt)","id_akce=$ida AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg.= "osoba už na této akci je".($funkce==99 ? ' jako pečovatel.
      Pokud má být na akci jako pomocný nebo osobní pečovatel nebo bude ve skupině G, je třeba ji
      na stránce Pečouni zrušit (zapamatovat poznámku k akci!) a odsud znovu zařadit.':'');
    goto end;
  }
  // pokud na akci ještě není, zjisti pro děti (<18 let) s_role a dite_kat
  list($datum_od,$ma_cenik)= select("datum_od,ma_cenik","akce","id_duakce=$ida");
  $vek= roku_k($narozeni,$datum_od);
  $kat= 0; $srole= 1;                                         // default= účastník, nedítě
  if     ( $role=='p' )                         { $kat= 0; $srole= 5; }   // osob.peč.
  elseif ( $vek>=18 || $narozeni=='0000-00-00') { $kat= 0; $srole= 1; }   // účastník
  elseif ( !$role || $role=='d' ) {                                       // děti podle čísleníku
    // odhad typu účasti dítěte podle stáří, role a organizace
    $dite_kat= xx_akce_dite_kat($ida);
    // {L|-},{c|p},{D|d|p|C} = lůžko/bez, celá/poloviční, s_role podle ms_akce_s_role
    $akce_dite_kat= map_cis($dite_kat,'data'); 
    $akce_dite_kat_LpD= map_cis($dite_kat,'barva'); 
    $akce_dite_kat_vek= map_cis($dite_kat,'ikona'); // od-do
    foreach ($akce_dite_kat as $kat) {
      list($od,$do)= explode('-',$akce_dite_kat_vek[$kat]);
      if ( $vek>=$od && $vek<$do) {
        $LpD= explode(',',$akce_dite_kat_LpD[$kat]);
        $srole= select('data','_cis',"druh='ms_akce_s_role' AND ikona='$LpD[2]' ");
        break;
      }
    }      
  }
  // přidej k pobytu
  $chng= array(
    (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
    (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
    (object)array('fld'=>'s_role',   'op'=>'i','val'=>$srole),
    (object)array('fld'=>'dite_kat', 'op'=>'i','val'=>$kat)
  );
  $ret->spolu= ezer_qry("INSERT",'spolu',0,$chng);
  # přidání do rodiny
  if ( $idr && $role ) {
    $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
    if ( $je ) { $ret->msg.= "osoba už v této rodině je"; goto end; }
    // pokud v rodině ještě není, přidej
    $ret->tvori= ezer_qry("INSERT",'tvori',0,array(
      (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
      (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
      (object)array('fld'=>'role',     'op'=>'i','val'=>$role)
    ));
  }
  # úprava access, je-li třeba
  if ( $access && $access!=$old_access) {
    ezer_qry("UPDATE",'osoba',$ido,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$old_access)
    ));
  }
  // POKUD je to akce s pobytem hrazeným podle ceníku Domu setkání - vytvoříme vzorec 
  if ($ma_cenik==2) {
//    dum_update_host($ret->spolu);
  }
end:
//                                                 debug($ret,'ucast2_pridej_osobu / $vek $kat $srole');
  return $ret;
}
/** ==========================================================================================> TISK */
# ------------------------------------------------------------------------------------ tisk2 sestava
# generování sestav - všechny sestavy s //! vynechávají nepřítomné na akci p.funkce IN (9,10,13,14,15)
function tisk2_sestava($akce,$par,$title,$vypis,$export=false,$hnizdo=0) { debug($par,"tisk2_sestava");
  global $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  return 0 ? 0
     : ( $par->typ=='p'    ? tisk2_sestava_pary($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='P'    ? akce2_sestava_pobyt($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='j'    ? tisk2_sestava_lidi($akce,$par,$title,$vypis,$export)   //!
//     : ( $par->typ=='vs'   ? akce2_strava_pary($akce,$par,$title,$vypis,$export)    //!
//     : ( $par->typ=='vsd'  ? akce2_strava_souhrn($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='vsd2' ? akce2_strava($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='vsd3' ? akce2_strava_vylet($akce,$par,$title,$vypis,$export)   //! 3.den děti oběd
     : ( $par->typ=='vv'   ? tisk2_text_vyroci($akce,$par,$title,$vypis,$export)    //!
     : ( $par->typ=='vi'   ? akce2_text_prehled($akce,$title)                       //!
     : ( $par->typ=='ve'   ? akce2_text_eko($akce,$par,$title,$vypis,$export)       //!
     : ( $par->typ=='vn'   ? akce2_sestava_noci($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='vp'   ? akce2_vyuctov_pary($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='vp2'  ? akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='vj'   ? akce2_stravenky($akce,$par,$title,$vypis,$export,$hnizdo)      //!
     : ( $par->typ=='vjp'  ? akce2_stravenky($akce,$par,$title,$vypis,$export,$hnizdo)      //!
     : ( $par->typ=='d'    ? akce2_sestava_pecouni($akce,$par,$title,$vypis,$export)//!
     : ( $par->typ=='ss'   ? tisk2_pdf_plachta($akce,$export,$hnizdo)                       //!
     : ( $par->typ=='s0'   ? tisk2_pdf_plachta0($export)                            // pomocné štítky
     : ( $par->typ=='skpl' ? akce2_plachta($akce,$par,$title,$vypis,$export,$hnizdo)//!
     : ( $par->typ=='skpr' ? akce2_skup_hist($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='skpopo'?akce2_skup_popo($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='skti' ? akce2_skup_tisk($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='12'   ? akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export) //!
     : ( $par->typ=='sd'   ? akce2_skup_deti($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='cz'   ? akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export)  // včetně náhradníků
     : ( $par->typ=='tab'  ? akce2_tabulka($akce,$par,$title,$vypis,$export)        //! předává se i typ=tab => náhradníci
     : ( $par->typ=='mrop' ? akce2_tabulka_mrop($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='stat' ? akce2_tabulka_stat($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='dot'  ? dot_prehled($akce,$par,$title,$vypis,$export,$hnizdo)  
     : ( $par->typ=='pok'  ? akce2_pokoje($akce,$par,$title,$vypis,$export,$hnizdo) 
     : ( $par->typ=='pri'  ? akce2_prihlasky($akce,$par,$title,$vypis,$export,$hnizdo) 
     : ( $par->typ=='nut'  ? akce2_hnizda($akce,$par,$title,$vypis,$export)         
     : (object)array('html'=>"<i>Tato sestava zatím není převedena do nové verze systému,
          <a href='mailto:martin@smidek.eu'>upozorněte mě</a>, že ji už potřebujete</i>")
     ))))))))))))))))))))))))))))));
}
# =======================================================================================> . seznamy
function mb_strcasecmp($str1, $str2, $encoding = null) {
    if (null === $encoding) { $encoding = mb_internal_encoding(); }
    return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}
# ------------------------------------------------------------------------------- akce2 tabulka
# generování tabulky účastníků $akce typu LK
function akce2_hnizda($akce,$par=null,$title='',$vypis='',$export=false) { trace();
  global $VPS, $tisk_hnizdo;
//                                         debug($par,"akce2_tabulka");
  $map_fce= map_cis('ms_akce_funkce','zkratka');
  $res= (object)array('html'=>'...');
  $info= akce2_info($akce,0,1);
//                                           debug($info,"info o akci"); //goto end;
  $clmn= $info->pobyty;
  if (!$clmn) return $res;
  // seřazení podle příjmení
  usort($clmn,function($a,$b) { return mb_strcasecmp($a->prijmeni,$b->prijmeni); });
  // odstranění jednoznačných jmen
  $clmn[-1]= (object)array('prijmeni'=>''); // zarážka
  $clmn[count($clmn)]= (object)array('prijmeni'=>''); // zarážka
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i-1]->prijmeni != $clmn[$i]->prijmeni
      && $clmn[$i+1]->prijmeni != $clmn[$i]->prijmeni ) {
      $clmn[$i]->jmena= '';
    }
  }
  unset($clmn[-1]); unset($clmn[count($clmn)]);
  // zkrácení zbylých jmen
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i]->jmena ) {
      list($m,$z)= explode(' ',$clmn[$i]->jmena);
      $clmn[$i]->jmena= mb_substr($m,0,1).'+'.mb_substr($z,0,1);
    }
  }
//                                           debug($clmn,"akce2_pobyty");
  $titl= array($VPS,'nováčci','repetenti','pečouni','organizace akce','náhradníci');
  foreach ($info->title as $h=>$hnizdo) {
    $tables.= "<h3>$hnizdo</h3>";
    $s_pary= array(0,0,0,0,0,0);
    $s_deti= array(0,0,0,0,0,0);
    $s_chuvy_d= array(0,0,0,0,0,0);
    $s_chuvy_p= array(0,0,0,0,0,0);
    $i= array(0,0,0,0,0,0);
    $tds= array();
    foreach ($clmn as $x) {
      if ($x->hnizdo==$h) {
        $sl= $x->typ=='vps' ? 0 : ($x->typ=='nov'?1:($x->typ=='rep'?2:($x->typ=='tym'?4:5)));
        $k= $i[$sl];
        $tds[$k][$sl]= $x->prijmeni;
        $s_pary[$sl]++;
        $x_deti= str_replace(',,',',',$x->deti);
        if ($x_deti!=='') {
          $tds[$k][$sl].= " ($x_deti)";
          $s_chuvy_d[$sl]+= substr_count($x_deti,'*');
          $s_chuvy_p[$sl]+= $_sp= substr_count($x_deti,'§');
          $s_deti[$sl]+= substr_count($x_deti,',') + 1 - $_sp;
        }
        if ($x->typ=='tym') {
          $tds[$k][$sl].= " / ".$map_fce[$x->fce];
        }
        $i[$sl]++;
      }
    }
    // doplníme pečouny
    $i3= 0;
    if ($info->pecouni) foreach ($info->pecouni as $x) {
      if ($x->hnizdo==$h) {
        $tds[$i3++][3]= "{$x->prijmeni} {$x->jmeno}";
      }
    }
    $i[3]= $i3;
    $ths= '';
    foreach ($titl as $k=>$t) {
      $tit= $k==3 ? je_1_2_5($i3,"pečoun,pečouni,pečounů") : (
          $k==4 ? $t : (je_1_2_5($s_pary[$k],"pár,páry,párů")." $t"));
      $ths.= "<th>$tit "
          . ($s_deti[$k] ? "+ {$s_deti[$k]} dětí ".($s_chuvy_d[$k] ? " ({$s_chuvy_d[$k]}* chůviček) " : '' ) : '' )
          . ($s_chuvy_p[$k] ? "+ {$s_chuvy_p[$k]}§ chův" : '' )
          . '</th>';
    }
    $trs= '';
    for ($j= 0; $j<max($i); $j++) {
      $trs.= '<tr>';
      for ($k= 0; $k<=5; $k++) {
        $trs.= '<td>'.($tds[$j][$k]).'</td>';
      }
      $trs.= '</tr>';
    }
    $tables.= "<div class='stat'><table class='stat'><tr>$ths</tr>$trs</table></div>";                                           
  }
end:  
  $legenda= "U páru je v závorce uveden věk dětí a chův, které berou na akci 
    <br>(§ označuje chůvu s rolí 'p' tj. snad dospělou, * označuje vlastní dítě sloužící coby chůvička). 
    <br>Odhad chybějících pečounů bere v úvahu chůvy a přihlášené pečouny a pro zbytek dětí 
    je počítán podle vzorce: 
    <br>1 pečoun na dítě do 3 let + na každé 3 děti jeden pečoun.";
  $res->html= "$legenda<br><br>$tables<br><br><br>"; 
  return $res;
}
# ------------------------------------------------------------------------------- akce2 tabulka
# generování tabulky účastníků $akce typu LK pro přípravu hnízd
# používá se i pro návrh skupinek
function akce2_tabulka($akce,$par,$title,$vypis,$export=false) { trace();
  global $VPS;
  $map_fce= map_cis('ms_akce_funkce','zkratka');
  $res= (object)array('html'=>'...',
      'vps'=>array(),'nevps'=>array(),'novi'=>array(),'druh'=>array(),'vice'=>array(),
      'problem'=>array(),'clmn'=>array());
  $clmn= tisk2_sestava_pary($akce,$par,$title,$vypis,false,true);
  if (!$clmn) return $res;
//                                         debug($clmn,"akce2_tabulka {$clmn[1]['prijmeni']}");
  // seřazení podle příjmení
  usort($clmn,function($a,$b) { return mb_strcasecmp($a['prijmeni'],$b['prijmeni']); });
//                                         debug($clmn,"akce2_tabulka");
  // odstranění jednoznačných jmen
  $clmn[-1]['prijmeni']= $clmn[count($clmn)]['prijmeni']= ''; // zarážky
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i-1]['prijmeni'] != $clmn[$i]['prijmeni']
      && $clmn[$i+1]['prijmeni'] != $clmn[$i]['prijmeni'] ) {
      $clmn[$i]['jmena']= '';
    }
  }
  unset($clmn[-1]); unset($clmn[count($clmn)-1]);
  // zkrácení zbylých jmen
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i]['jmena'] ) {
      list($m,$z)= explode(' ',$clmn[$i]['jmena']);
//      $clmn[$i]['jmena']= $m[0].'+'.$z[0];
      $clmn[$i]['jmena']= mb_substr($m,0,1).'+'.mb_substr($z,0,1);
    }
  }
  // vložení do tabulky
  $tab= array();
  for ($i= 0; $i<count($clmn); $i++) {
    $ci= $clmn[$i];;
    $x= $ci['x_ms'];
    $v= $ci['_vps'];
    $f= $ci['funkce'];
    $c= $f==9 ? 6 : ($f!=0 && $f!=1 && $f!=2 ? 7
     : ($f==1 ? 0 : ($v=='(vps)'||$v=='(pps)' ? 5
     : ($x==1 ? 1 : ($x==2 ? 2 : ($x==3 ? 3 : 4))))));
    $tab[$c][]= $i;
    // definice sloupců v res
//    $ci['key_rodina']= $ci['prijmeni'];
    if ($f==1) $res->vps[]= $ci['^id_pobyt'];
    elseif ($v=='(vps)'||$v=='(pps)' || $f==5) $res->nevps[]= $ci['^id_pobyt']; 
    elseif ($x==1) $res->novi[]= $ci['^id_pobyt'];
    elseif ($x==2) $res->druh[]= $ci['^id_pobyt'];
    elseif ($x>=3) $res->vice[]= $ci['^id_pobyt'];
    elseif ($f<2) $res->problem[]= $ci['^id_pobyt'];
    $res->clmn[$ci['^id_pobyt']]= $ci;
  }
//                                            debug($res,'návrh skupinek');
  // export HTML a do Excelu
  $ids= array(
    "$VPS:22","Prvňáci:14","Druháci:14","Třeťáci:14","Víceročáci:14",
    "$VPS mimo službu:22","Náhradníci:14","Ostatní:26");
  $max_r= 0;
  for ($c= 0; $c<=7; $c++) {
    list($id)= explode(':',$ids[$c]);
    $ths.= "<th>$id (".(isset($tab[$c]) ? count($tab[$c]) : '').")</th>";
    $max_r= max($max_r,isset($tab[$c]) ? count($tab[$c]) : 0);
  }
  for ($r= 0; $r<$max_r; $r++) {
    $trs.= "<tr>";
    for ($c= 0; $c<=7; $c++) {
      if ( isset($tab[$c][$r]) ) {
        $i= $tab[$c][$r];
        $ci= $clmn[$i]; $x= $ci['x_ms']; $v= $ci['_vps']; $f= $ci['funkce']; $idr= $ci['key_rodina'];
        $style= 
            $v   ? " style='background-color:yellow'" : ''; //(
//            $f>1 ? " style='background-color:violet'" : '');
        $ucasti= $c==7 ? "($map_fce[$f])" : ($c==4 ? "($x)" : '');
        // počet služeb a rok odpočinku VPS
        $sluzby= $poprve= '';
        if ( $c==0 || $c==5 ) {
          $akt= akce2_skup_paru($idr);
          $sluzby= "({$akt->sluzba},{$akt->odpocinek})";
          $poprve= $akt->vps==0 ? '* ' : '';
        }
        $prijmeni_plus= "$poprve{$ci['prijmeni']} {$ci['jmena']} $ucasti $sluzby";
        $trs.= "<td$style>$prijmeni_plus</td>";
        $clmn[$i]['prijmeni']= $prijmeni_plus;
      }
      else {
        $trs.= "<td></td>";
      }
    }
    $trs.= "</tr>";
  }
//                                         debug($tab,"akce2_tabulka - tab");
//                                         debug($clmn,"akce2_tabulka - clmn");
//                                         debug($res,"akce2_tabulka - bez html");
  if ( $export ) {
    $rc= $rc_atr= $n= $tit= array();
    for ($c= 0; $c<=7; $c++) {
      $n[$c]= 0;
      for ($r= 0; $r<$max_r; $r++) {
        $rc[$r][$c]= '';
      }
    }
    foreach ($tab as $c => $radky) {
      foreach ($radky as $r=>$ucastnik) {
        $rc[$r][$c]= $clmn[$ucastnik]['prijmeni'];
        if ( $clmn[$ucastnik]['_vps'] )
          $rc_atr[$r][$c]= ' bcolor=ffffff77';
//        elseif ( $clmn[$ucastnik]['funkce'] > 1)
//          $rc_atr[$r][$c]= ' bcolor=ffff77ff';
        $n[$c]++;
      }
    }
    for ($c= 0; $c<=7; $c++) {
      list($id,$len)= explode(':',$ids[$c]);
      $tit[$c]= "$id ($n[$c]):$len";
    }
    $res->tits= $tit;
    $res->flds= explode(',',"0,1,2,3,4,5,6,7");
    $res->clmn= $rc;
    $res->atrs= $rc_atr;
    $res->expr= null;
//                                         debug($res,"akce2_tabulka - res");
  }
  $legenda= "VPS jsou označeny žlutě a hvězdička označuje nové; <br>v závorce je "
      . "u VPS počet služeb bez odpočinku a rok posledního odpočinku, "
      . "u víceročáků počet účastí, "
      . "u ostatních funkce na kurzu";
  $res->html= "$legenda<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$trs</table></div>";
  return $res;
}
# ---------------------------------------------------------------------------- akce2 starsi_mrop_pdf
# generování skupinky MROP - pro starší
# pokud je zadáno id_pobyt jedná se o VPS a navrátí se je grp jeho skupinky (personifikace mailu)
function akce2_starsi_mrop_pdf($akce,$id_pobyt_vps=0,$tj='MROP') { trace();
  global $ezer_path_docs;
  $res= (object)array('html'=>'','err'=>'');
  if ($id_pobyt_vps) {
    $skupina= select('skupina','pobyt',"id_pobyt=$id_pobyt_vps");
    $clenove= "";
  }
  $cond= $id_pobyt_vps ? "skupina=$skupina" : 1;
  $grp= $cht= array();
  // data akce
  list($datum_od,$statistika)= select('datum_od,statistika','akce',"id_duakce=$akce");
  if ($statistika!=1) { $res->err= "tato sestava je jen pro akce typu 'Křižanov' "; goto end; }
  $rok= substr($datum_od,0,4);
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $rg= pdo_qry("
    SELECT
      jmeno,prijmeni,skupina,pokoj,budova,funkce,p.id_pobyt,pracovni,
      -- ROUND(DATEDIFF('$datum_od',o.narozeni)/365.2425,0) AS vek,
      ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum_od',o.narozeni)/365.2425,YEAR('$datum_od')-YEAR(o.narozeni)),0) AS vek,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      TRIM(IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony))) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily
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
    WHERE p.id_akce=$akce AND $cond AND p.funkce IN (0,1,2)
    ORDER BY skupina,p.funkce DESC,jmeno");
  while ( $rg && ($x= pdo_fetch_object($rg)) ) {
    if ($id_pobyt_vps && $tj=='EROP') {
      $nik_missing= $x->pracovni ? '' : " ... <b>KONTAKT?</b>";
      $nik= '';
      if (!$nik_missing) {
        $m= null;
        if (preg_match('~Jméno:\s*(.+)(?:\nSymbol:\s*(.+)|)(?:\nJazyk:\s*(.+)|)~u',$x->pracovni,$m)) {
          $nik= "<i>$m[1]</i> ";
        }
      }
      $obec= $x->obec ?: 'CZ';
      if ($id_pobyt_vps==$x->id_pobyt) {
        $clenove= "Skupina $skupina, stoker $x->jmeno $nik $x->prijmeni <table>".$clenove;
      }
      else {
        $clenove.= "<tr><td>$nik_missing $x->jmeno $nik $x->prijmeni ($x->vek)</td>
          <td>$x->telefony</td><td>$x->emaily</td>
          <td>$x->psc $obec, $x->stat</td></tr>";
      }
    }
    else {
      $grp[$x->skupina][]= $x;
    }
    $chata= $x->pokoj;
    if (!isset($cht[$x->skupina])) $cht[$x->skupina]= array();
    if ($chata) {
      if (!in_array($chata,$cht[$x->skupina])) $cht[$x->skupina][]= $chata;
    }
  }
//  debug($grp,"sestava pro starší");
  if ($id_pobyt_vps) {
    $res->skupina= "$clenove</table>";
//    display($res->skupina);
    goto end;
  }
  // redakce
  $neni= array();
  $fname= "mrop_$rok-skupiny.pdf";
  $fpath= "$ezer_path_docs/$fname";
  $hname= "mrop_$rok-skupiny.html";
  $h= fopen("$ezer_path_docs/$hname",'w');
  fwrite($h,chr(0xEF).chr(0xBB).chr(0xBF)."<html lang='cs'><body>");
  $starsi= "<h3>Adresář starších</h3>";
  $res->html= 
      "Sestava skupin pro tisk a rozdání starším je <a href='docs/$fname' target='pdf'>zde</a>,
      <br>sestava pro ctrl-c/ctrl-v pro vložení do individuálního mailu starším je
      <a href='docs/$hname' target='html'>zde</a><hr>";
  tc_html_open('L');
  $pata= "<i>iniciace $rok</i>";
  foreach ($grp as $g) {
    $g0= $g[0];
    $skupina= $g0->skupina;
    $page= "<h3>Skupina $skupina".($cht[$skupina] ? " má chatky ".implode(', ',$cht[$skupina]):'')." </h3>
      <table style=\"width:29cm\">";
    fwrite($h,"<h3>Skupina $skupina</h3>");
    foreach ($g as $o) {
      if (!$skupina) { $neni[]= "$o->prijmeni $o->jmeno"; }
//      $chata= $o->pokoj ?: '';
      $chata= $o->budova ? "$o->budova $o->pokoj" : ($o->pokoj ?: '');
      $fill= '&nbsp;&nbsp;';
      $stat= $o->stat=='CZ' ? '' : ", $o->stat";
      if ($tj=='EROP') {
        $nik_missing= $o->pracovni ? '' : " ... <b>KONTAKT?</b>";
        $nik= '';
        $jazyk= '';
        if (!$nik_missing) {
          $m= null;
          if (preg_match('~Jméno:\s*(.+)(?:\nSymbol:\s*(.+)|)(?:\nJazyk:\s*(.+)|)~u',$o->pracovni,$m)) {
            $nik= "<i>$m[1]</i> ";
            if ($m[3]) {
              $jazyk.= preg_match('~ang~iu',$m[3]) ? 'A' : '';
              $jazyk.= preg_match('~něm~iu',$m[3]) ? 'N' : '';
              if (!$jazyk) $res->err.= "POZOR - $o->jmeno $o->prijmeni zná divný jazyk<br>";
              else $jazyk= ", $jazyk";
            }
          }
          else {
            $res->err.= "POZOR - $o->jmeno $o->prijmeni má chybně zapsané jméno a symbol<br>";
          }
        }
      }
      $jmeno= $o->funkce 
          ? "<td align=\"right\"><big><b>$o->jmeno</b></big> $nik$o->prijmeni ($o->vek)</td>" 
          : "<td><big><b>$o->jmeno</b></big> $nik$o->prijmeni ($o->vek$jazyk) $nik_missing</td>";
      $page.= "<tr>
          <td width=\"60\">$chata</td>
          $jmeno
          <td>$fill$o->telefony</td>
          <td>$o->emaily$fill</td>
          <td>$o->psc $o->obec $stat<br></td>
        </tr>";
      $adresa= "$o->jmeno $o->prijmeni, $o->telefony, $o->emaily, $o->psc $o->obec $stat<br>";
      fwrite($h,$adresa);
      if ($o->funkce) $starsi.= $adresa;
    }
    $page.= "</table>";
    tc_html_write($page,$pata);
    $res->html.= $page;
  }
  // hlášení neumístěných sojka>11, 
  if (count($neni)) {
//    debug($neni,"sirotci");
    $res->err.= "POZOR - tito chlapi nejsou ve skupině: ".implode(',',$neni);
  }
  // tisk
  tc_html_close($fpath);
  fwrite($h,"$starsi</body></html>");
  fclose($h);
end:  
  return $res;
}
# ------------------------------------------------------------------------------- akce2 tabulka_mrop
# generování tabulky účastníků $akce typu MROP - rozpis chatek
function akce2_tabulka_mrop($akce,$par,$title,$vypis,$export=false) { debug($par,'akce2_tabulka_mrop');
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $grp= isset($par->grp) ? $par->grp : '';
  $ord= isset($par->ord) ? $par->ord : '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $clmn= array();
  // pokud je grp sdruž podle chatek
  $GROUP= $grp ? "GROUP BY $grp" : 'GROUP BY id_pobyt';
  $GROUP_CONCAT= $grp ? "GROUP_CONCAT(CONCAT(prijmeni,' ',jmeno) ORDER BY prijmeni) AS jmena," : '';
  // data akce
  $qry=  "
    SELECT $GROUP_CONCAT       
      CONCAT(prijmeni,' ',jmeno) AS pr_jm,jmeno,prijmeni,
      skupina,pokoj,IFNULL(SUM(u_castka),0) AS platba,
      p.poznamka,p.pracovni,o.email,
       IFNULL(SUBSTR((SELECT MIN(CONCAT(role,spz))
        FROM tvori AS ot JOIN rodina AS r USING (id_rodina) WHERE ot.id_osoba=o.id_osoba
      ),2),'') AS spz,'' AS filler
    FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN uhrada AS u USING (id_pobyt)
    WHERE p.id_akce=$akce AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
    $GROUP
    ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $clmn[$n][$f]= $x->$f;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ------------------------------------------------------------------------------- tisk2 sestava_pary
# generování sestavy pro účastníky $akce - rodiny, pokud je par.rodiny=1 pak poze páry s dětmi
# jedině pokud je $par->typ='tab' zobrazí i náhradníky
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function tisk2_sestava_pary($akce,$par,$title,$vypis,$export=false,$internal=false) { trace();
  global $EZER, $tisk_hnizdo, $VPS;
                                                                display("tisk hnizda $tisk_hnizdo");
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, 
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta;
  $result= (object)array();
  $typ= $par->typ;
  $tit= isset($par->tit) ? $par->tit : '';
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  if ( $tisk_hnizdo ) $cnd.= " AND hnizdo=$tisk_hnizdo ";
//  $cnd= "id_pobyt=54261"; // 
  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $ord= isset($par->ord) ? $par->ord : "a _nazev";
  $fil= isset($par->filtr) ? $par->filtr : null;
  $html= '';
  $href= '';
  $n= 0;
  // hnízda
  list($hnizda,$org)= select('hnizda,access','akce',"id_duakce=$akce");
  $hnizda= $hnizda ? explode(',',$hnizda) : null;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  $c_dite_kat= $org==2
      ? map_cis('fa_akce_dite_kat','zkratka') 
      : map_cis('ys_akce_dite_kat','zkratka');  
  $c_akce_dieta= map_cis('ms_akce_dieta','zkratka');  
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
    'cmd'=>'browse_load',
    'cond'=>"$cnd AND p.id_akce=$akce AND p.funkce NOT IN "
      . ($par->typ=='tab' ? "(10,13,14)" : "(9,10,13,14,15)")
//      . " AND p.id_pobyt=62834"
      ,
    'having'=>$hav,'order'=>$ord,
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
//  /**/                                                   debug($y);
  # rozbor výsledku browse/ask
  array_shift($y->values);
  foreach ($y->values as $x) {
    // aplikace neosobních filtrů
    if ( $fil && $fil->r_umi ) {
      $umi= explode(',',$x->r_umi);
      if ( !in_array($fil->r_umi,$umi) ) continue;
    }
//     // ke spočítaným účastím přidej r_ms
//     if ( $fil && $fil->ucasti_ms ) {
//       $ru= pdo_qry("SELECT COUNT(*)+r_ms as _pocet FROM akce AS a
//               JOIN pobyt AS p ON a.id_duakce=p.id_akce
//               JOIN rodina AS r ON r.id_rodina=p.i0_rodina
//               WHERE a.druh=1 AND p.i0_rodina={$x->key_rodina} AND a.datum_od<='{$x->datum_od}'");
//       $xu= pdo_fetch_object($ru);
//       if ( $xu->_pocet!=$fil->ucasti_ms ) continue;
//     }
    // pokračování, pokud záznam vyhověl filtrům
    # rozbor osobních údajů: adresa nebo základní kontakt se získá 3 způsoby
    # 1. první osoba má osobní údaje - ty se použijí
    # 2. první osoba má rodinné údaje, které se shodují s i0_rodina - použijí se ty z i0_rodina
    # 3. první osoba má rodinné údaje, které se neshodují s i0_rodina - použijí se tedy její
    $telefony= $emaily= array();
    if ( $x->r_telefony ) $telefony[]= trim($x->r_telefony,",; ");
    if ( $x->r_emaily )   $emaily[]=   trim($x->r_emaily,",; ");
    # rozšířené spojení se získá slepením údajů všech účastníků
    $xs= explode('≈',$x->r_cleni);
//    /**/                                                 debug($x);
    $pocet= 0;
    $spolu_note= "";
    $osoba_note= "";
    $cleni= array(); // změna indexu z jmeno na id_spolu
    $deti= array();
    $rodice= array();
    $vek_deti= array();
//                                                         if ( $x->key_pobyt==32146 ) debug($x);
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
//    /**/                                                 debug($o);
//                                                         if ( $x->key_pobyt==32146 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $pocet++;
        $jmeno= str_replace(' ','-',$o[$i_osoba_jmeno]);
        if ( $o[$i_spolu_note] ) $spolu_note.= " + $jmeno:$o[$i_spolu_note]";
        if ( $o[$i_osoba_note] ) $osoba_note.= " + $jmeno:$o[$i_osoba_note]";
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_telefon] )
          $telefony[]= trim($o[$i_osoba_telefon],",; ");
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_email] )
          $emaily[]= trim($o[$i_osoba_email],",; ");
        if ( $x->key_rodina ) {
//          $cleni[$o[$i_osoba_jmeno]]['dieta']= $c_akce_dieta[$o[$i_osoba_dieta]]; 
          $cleni[$o[$i_key_spolu]]['dieta']= $c_akce_dieta[$o[$i_osoba_dieta]]; 
          $cleni[$o[$i_key_spolu]]['jmeno']= $o[$i_osoba_jmeno]; 
          if ( $o[$i_osoba_role]=='a' || $o[$i_osoba_role]=='b' ) {
            $rodice[$o[$i_osoba_role]]['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice[$o[$i_osoba_role]]['prijmeni']= trim($o[$i_osoba_prijmeni]);
            $rodice[$o[$i_osoba_role]]['telefon']= trim($o[$i_osoba_telefon],",; ");
            $rodice[$o[$i_osoba_role]]['obcanka']= trim($o[$i_osoba_obcanka]);
          }
          if ( $o[$i_osoba_role]=='d' ) {
            $vek_deti[]= $o[$i_osoba_vek];
            $deti[$i]['jmeno']= $o[$i_osoba_jmeno];
            $deti[$i]['vek']= $o[$i_osoba_vek];
            $deti[$i]['kat']= $c_dite_kat[$o[$i_spolu_dite_kat]]; 
          }
        }
        else {
            $rodice['a']['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice['a']['prijmeni']= trim($o[$i_osoba_prijmeni]);
        }
      }
    }
//    /**/                                                 debug($rodice,"RODIČE");
//    /**/                                                 debug($deti,"DĚTI");
//    /**/                                                 debug($cleni,"ČLENI");
    $o= explode('~',$xs[0]);
    // show: adresa, ulice, psc, obec, stat, kontakt, telefon, nomail, email
    $io= $i_adresa;
    $adresa=  $o[$io++]; $ulice= $o[$io++]; $psc= $o[$io++]; $obec= $o[$io++]; $stat= $o[$io++];
    $kontakt= $o[$io++]; $telefon= $o[$io++]; $nomail= $o[$io++]; $email= $o[$io++];
    // úpravy
    $emaily= count($emaily) ? implode(', ',$emaily).';' : '';
    $email=  trim($kontakt ? $email   : substr($email,$r),",; ") ?: $emaily;
    $emaily= $emaily ?: $email;
    $telefony= count($telefony) ? implode(', ',$telefony).';' : '';
    $telefon=  trim($kontakt ? $telefon : substr($telefon,$r),",; ") ?: $telefony;
    $telefony= $telefony ?: $telefon;
//                                                         if ( $x->key_pobyt==22141 )
//                                                         display("email=$email, emaily=$emaily, telefon=$telefon, telefony=$telefony");
    // pokud je omezení na rodiny s dětmi
    if ( $par->rodiny && $pocet<=2 ) {
      continue;
    }  
    // přepsání do výstupního pole
    $n++;
    $clmn[$n]= array();
    $r= 0; // 1 ukáže bez (r)
    foreach($flds as $f) {          // _pocet,poznamka,note
      $c= '';
      switch ($f) {
      case '^id_pobyt': $c= $x->key_pobyt; break;
      case 'hnizdo':    $c= $hnizda ? ($x->hnizdo ? $hnizda[$x->hnizdo-1] : '?') : '-'; break;
      case 'prijmeni':  $c= $x->_nazev; break;
      case 'jmena':     $c= $x->_jmena; break;
      case 'rodice':    $c= count($rodice)==2 && strpos($x->_nazev,'-')
                          ? "{$rodice['a']['jmeno']} {$rodice['a']['prijmeni']}
                             a {$rodice['b']['jmeno']} {$rodice['b']['prijmeni']}" : (
        count($rodice)==2 ? "{$rodice['a']['jmeno']} a {$rodice['b']['jmeno']} {$x->_nazev}" : (
             $rodice['a'] ? "{$rodice['a']['jmeno']} {$rodice['a']['prijmeni']}" : (
             $rodice['b'] ? "{$rodice['b']['jmeno']} {$rodice['b']['prijmeni']}"
                          : $x->_nazev )));
                        break;
      case 'jmena2':    $c= explode(' ',$x->_jmena);
                        $c= $c[0].' '.$c[1]; break;
      case 'vek_deti':  $c= implode(',',$vek_deti); break;
      case 'ulice':     $c= $adresa  ? $ulice   : substr($ulice,$r); break;
      case 'psc':       $c= $adresa  ? $psc     : substr($psc,$r);   break;
      case 'obec':      $c= $adresa  ? $obec    : substr($obec,$r);  break;
      case 'stat':      $c= $adresa  ? $stat    : substr($stat,$r);
                        if ( $c=='CZ' ) $c= '';
                        break;
      case 'telefon':   $c= $telefon;  break;
      case 'telefony':  $c= $telefony; break;
      case '*telefony': foreach($rodice as $X) {
                          if (!$X['telefon']) continue;
                          $c.= "{$X['jmeno']}:{$X['telefon']} ";
                        }; break;
      case '*obcanky':  foreach($rodice as $X) {
                          if (!$X['obcanka']) continue;
                          $c.= "{$X['jmeno']}:{$X['obcanka']} ";
                        }; break;
      case '*deti':     foreach($deti as $X) {
                          $c.= "{$X['jmeno']}:{$X['vek']}:{$X['kat']} ";
                        }; break;
      case '*diety':    foreach($cleni as $X) { 
                          if ($X['dieta']=='-') continue;
                          $c.= "{$X['jmeno']}:{$X['dieta']} ";
                        }; break;
      case 'email':     $c= $email;  break;
      case 'emaily':    $c= $emaily; break;
      case '_pocet':    $c= $pocet; break;
      case 'poznamka':  $c= $x->p_poznamka . ($spolu_note ?: ''); break;
      case 'note':      $c= $x->r_note . ($osoba_note ?: ''); break;
      case 'pok1':      list($c)= explode(',',$x->pokoj); $c= trim($c); break;
      case 'pok2':      list($_,$c)= explode(',',$x->pokoj); $c= trim($c); break;
      case 'pok3':      list($_,$_,$c)= explode(',',$x->pokoj); $c= trim($c); break;
      case '_diety':    $c=  $x->strava_cel_bm!=0  || $x->strava_pol_bm!=0 ? '_bm' : '';
                        $c.= $x->strava_cel_bl!=0  || $x->strava_pol_bl!=0 ? '_bl' : ''; break;
      case '_vyjimky':  $c= $x->cstrava_cel!=''    || $x->cstrava_pol!=''
                         || $x->cstrava_cel_bm!='' || $x->cstrava_pol_bm!=''
                         || $x->cstrava_cel_bl!='' || $x->cstrava_pol_bl!='' ? 1 : 0; break;
      case '_vps':      $VPS_= $access==1 ? 'VPS' : 'PPS'; $vps_= $access==1 ? '(vps)' : '(pps)';
                        $c= $x->funkce==1 ? $VPS_ : (strpos($x->r_umi,'1')!==false ? $vps_ : ''); break;
      default:          $c= $x->$f; break;
      }
      $clmn[$n][$f]= $c;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  $res= $internal ? $clmn : tisk2_table($tits,$flds,$clmn,$export);
  return $res;
}
# -------------------------------------------------------------------------------- tisk_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce ... jen skutečně na akci
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
#   $par->sel = seznam id_pobyt
#   $par->subtyp = pro EROP se dopčítávají sloupce účast na MS a účast na mužské akci
function tisk2_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $result= (object)array();
  $typ= $par->typ;
  $subtyp= isset($par->subtyp) ? $par->subtyp : '';
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  if ( $tisk_hnizdo ) $cnd.= " AND IF(funkce=99,s_hnizdo=$tisk_hnizdo,hnizdo=$tisk_hnizdo)";
  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),o.prijmeni,o.jmeno";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $cirkev= map_cis('ms_akce_cirkev','zkratka');  $cirkev[0]= '';
  $vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $vzdelani[0]= '';
  $funkce= map_cis('ms_akce_funkce','zkratka');  $funkce[0]= '';
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  $dieta= map_cis('ms_akce_dieta','zkratka');  $dieta[0]= '';
  $dite_kat= xx_akce_dite_kat($akce);
  $dite_kat= map_cis($dite_kat,'zkratka');  $dite_kat[0]= '?';
  $s_role= map_cis('ms_akce_s_role','zkratka');  $s_role[0]= '?';
  // načtení ceníku pro dite_kat, pokud se chce _poplatek
  if ( strpos($fld,"_poplatek") ) {
    $soubezna= select("id_duakce","akce","id_hlavni=$akce");
    if ($soubezna) akce2_nacti_cenik($soubezna,$tisk_hnizdo,$cenik,$html);
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
  if ( isset($par->sel) && $par->sel ) {
//                                                 display("i.par.sel=$par->sel");
    $cnd.= $par->selected ? " AND p.id_pobyt IN ($par->selected)" : ' AND 0';
  }
  // data akce
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $qry=  "
    SELECT
      p.pouze,p.poznamka,p.pracovni,/*p.platba - není atribut osoby!,*/p.funkce,p.skupina,p.pokoj,p.budova,s.s_role,
      o.id_osoba,o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,o.prislusnost,o.obcanka,o.clen,o.dieta,
      IFNULL(r2.id_rodina,r1.id_rodina) AS id_rodina, r3.role AS p_role,
      IFNULL(r2.nazev,r1.nazev) AS r_nazev,
      IFNULL(r2.spz,r1.spz) AS r_spz,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily,
      s.poznamka AS s_note,s.pfunkce,s.dite_kat,s.skupinka,
      IFNULL(r2.note,r1.note) AS r_note,
      IFNULL(r2.role,r1.role) AS r_role,
      ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek,
      (SELECT GROUP_CONCAT(prijmeni,' ',jmeno)
        FROM akce JOIN pobyt ON id_akce=akce.id_duakce
        JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
        JOIN osoba ON osoba.id_osoba=spolu.id_osoba
        WHERE spolu.pecovane=o.id_osoba AND id_akce=$akce) AS _chuva,
      (SELECT CONCAT(prijmeni,' ',jmeno) FROM osoba
        WHERE s.pecovane=osoba.id_osoba) AS _chovany,
        cirkev,vzdelani,zamest,zajmy
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
      -- r3=rodina, která je na akci
      LEFT JOIN ( SELECT id_osoba,id_rodina,role
        FROM tvori JOIN rodina USING (id_rodina))
        AS r3 ON r3.id_osoba=o.id_osoba AND r3.id_rodina=p.i0_rodina
      -- akce
      JOIN akce AS a ON a.id_duakce=p.id_akce
    WHERE p.id_akce=$akce AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
      GROUP BY o.id_osoba $hav
      ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    // doplnění položek pro subtyp=EROP
    $historie= '';
    if ($subtyp=='EROP') {
      list($akce_ms,$akce_vps,$akce_ch,$iniciace,$firming,$cizi)= select(
          "SUM(IF(druh=1,1,0)),SUM(IF(funkce=1,1,0)),SUM(IF(statistika>1,1,0)),iniciace,firming,prislusnost",
          'osoba JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce',
          "id_osoba={$x->id_osoba} AND zruseno=0 AND datum_od<'2023-09-01' ");
      $historie= '';
      if (!$cizi) {
        if ($akce_ch) $historie.= " chlapi $akce_ch x";
        if ($akce_ms) $historie.= " MS $akce_ms x";
        if ($akce_vps) $historie.= ' (vps)';
        if ($firming) $historie.= " firming $firming";
        $historie.= " iniciace $iniciace";
      }
      $x->funkce= $x->funkce==1 ? 'stoker' : ($x->funkce==12 ? 'lektor' : ($x->funkce==5 ? 'hospodář' : $x->funkce==1));
    }
    // doplnění počítaných položek
    $x->narozeni_dmy= sql_date_year($x->narozeni);
    foreach($flds as $f) {
      switch ($f) {
      case '_historie':                                               // historie na akcích
        $clmn[$n][$f]= $historie;
        break;
      case '1':                                                       // 1
        $clmn[$n][$f]= 1;
        break;
      case 'prislusnost':                                             // stát.příslušnost: osoba
      case 'stat':                                                    // stát: rodina/osoba
        $clmn[$n][$f]= $x->$f ?: 'CZ';
        break;
      case 'dieta':                                                   // osoba: dieta
        $clmn[$n][$f]= $dieta[$x->$f];
        break;
      case 'cirkev':                                                  // osoba: církev
        $clmn[$n][$f]= $cirkev[$x->$f];
        break;
      case 'vzdelani':                                                // osoba: vzdělání
        $clmn[$n][$f]= $vzdelani[$x->$f];
        break;
      case '_n_deti':                                                 // osoba: počet dětí
        $ido= $x->id_osoba;
        list($n_deti,$je_idr)= select('COUNT(*),IFNULL(rodic.id_rodina,-1)',
            'tvori AS rodic
              JOIN rodina AS r ON r.id_rodina=rodic.id_rodina
              JOIN tvori AS dite ON dite.id_rodina=r.id_rodina',
            "rodic.role IN ('a','b') AND dite.role='d' AND rodic.id_osoba=$ido");
        $clmn[$n][$f]= $je_idr==-1 ? '?' : $n_deti;
        break;
      case 'dite_kat':                                                // osoba: kategorie dítěte
        $clmn[$n][$f]= in_array($x->s_role,array(2,3,4)) 
          ? $s_role[$x->s_role].'-'.$dite_kat[$x->$f]
          : $s_role[$x->s_role];
//        $clmn[$n][$f]= $dite_kat[$x->$f];
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
        $clmn[$n][$f]= substr($nar,2,2).substr($nar,5,2).substr($nar,8,2);
        break;
      case '_ymca':
        $clmn[$n][$f]= $x->clen ? $x->clen : '';
        break;
      case '_funkce':
        $clmn[$n][$f]= $funkce[$x->funkce];
        break;
      case 'pfunkce':
        $pf= $x->$f;
        $clmn[$n][$f]= !$pf ? 'skupinka' : (
            $pf==4 ? 'pomocný p.' : (
            $pf==5 || $pf==95 ? "os.peč. pro: {$x->_chovany}" : (
            $pf==8 ? 'skupina G' : (
            $pf==92 ? "os.peč. je: {$x->_chuva}" : '?'))));
        break;
      case '_typ':                                      // 1: dítě, pečoun  2: zbytek
        $clmn[$n][$f]= $x->funkce==99 ? 1 : (
                       $x->_vek<18 ? 1 : 2);
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
        $clmn[$n][$f]= str_replace('-','/',$x->narozeni);
        break;
      default: $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ---------------------------------------------------------------------------- akce2 sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= isset($par->ord) && $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
  $html= '';
  $href= '';
  $n= 0;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  _akce2_sestava_pecouni($clmn,$akce,$fld,$cnd,$ord);
  // dekódování parametrů
  $flds= explode(',',$fld);
  $tits= explode(',',$tit);
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ------------------------------------------------------------------------------ akce2 sestava_pobyt
# generování sestavy pro účastníky $akce se stejným pobytem - jen Dům 
#   $fld = seznam položek s prefixem (platba se nikde nepoužívá)
#   $cnd = podmínka
function akce2_sestava_pobyt($akce,$par,$title,$vypis,$export=false) { debug($par,'akce2_sestava_pobyt');
  $otoc= function ($s) {
    mb_internal_encoding("UTF-8");
    $s= mb_strtolower($s);
    $x= '';
    for ($i= mb_strlen($s); $i>=0; $i--) {
      $xi= mb_substr($s,$i,1);
      $xi= mb_strtoupper($xi);
      $x.= $xi;
    }
    return $x;
  };
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= isset($par->cnd) ? $par->cnd : 1;
  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $ord= isset($par->ord) ? $par->ord : "nazev";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
//  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT id_pobyt,
            r.nazev as nazev,p.pouze as pouze,p.poznamka,
            -- p.datplatby,p.zpusobplat,
            COUNT(o.id_osoba) AS _pocet,
            SUM(IF(t.role IN ('a','b'),1,0)) AS _pocetA,
            GROUP_CONCAT(DISTINCT o.prijmeni ORDER BY t.role DESC) as _prijmeni,
            GROUP_CONCAT(IF(o.jmeno='','?',o.jmeno)    ORDER BY t.role DESC) as _jmena,
            GROUP_CONCAT(o.email    ORDER BY t.role DESC SEPARATOR ';') as _emaily,
            GROUP_CONCAT(o.telefon  ORDER BY t.role DESC SEPARATOR ';') as _telefony,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti,
            SUM(IF(t.role='d',1,0)) as _deti,
            r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.note/* AS r_note*/,p.skupina,
            p.ubytovani,p.budova,p.pokoj,
            p.luzka,p.kocarek,p.pristylky,p.strava_cel,p.strava_pol,
            GROUP_CONCAT(IFNULL((SELECT CONCAT(osoba.jmeno,' ',osoba.prijmeni)
              FROM pobyt
              JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
              JOIN osoba ON osoba.id_osoba=spolu.id_osoba
              WHERE pobyt.id_akce='$akce' AND spolu.pecovane=o.id_osoba),'') SEPARATOR ' ') AS _chuvy,
            prednasi
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          -- LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN rodina AS r ON r.id_rodina=IF(p.i0_rodina,p.i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
          GROUP BY id_pobyt $hav
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $x->prijmeni= $x->nazev ?: $x->_prijmeni;
    $x->jmena=    $x->_jmena;
    $x->_pocet=   $x->_pocet;
    // podle číselníku
    $x->ubytovani= $c_ubytovani[$x->ubytovani];
    $x->prednasi= $c_prednasi[$x->prednasi];
//    $x->zpusobplat= $c_platba[$x->zpusobplat];
    // ceny DS
    $cena= dum_browse_pobyt((object)['cmd'=>'suma','cond'=>"id_pobyt=$x->id_pobyt"]);
    $abbr= $cena->abbr;
    // další
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case 'ubyt': case 'stra': case 'popl': case 'prog': 
                        $clmn[$n][$f]= $abbr[$f]; break;
      case 'celkem':    $clmn[$n][$f]= $cena->$f; break;
      case '_pocetD':   $clmn[$n][$f]= $x->_pocet - $x->_pocetA; break;
      case '=par':      $clmn[$n][$f]= "{$x->prijmeni} {$x->jmena}"; break;
      // fonty: ISOCTEUR, Tekton Pro
      case '=pozpatku': $clmn[$n][$f]= $otoc("{$x->prijmeni} {$x->jmena}"); break;
      default:          $clmn[$n][$f]= $x->$f; break;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------- _akce2 sestava_pecouni
# výpočet pro generování sestavy pro účastníky $akce - pečouny a pro statistiku
function _akce2_sestava_pecouni(&$clmn,$akce,$fld='_skoleni,_sluzba,_reflexe',$cnd=1,$ord=1) { trace();
  global $tisk_hnizdo;
  if ( $tisk_hnizdo ) $cnd.= " AND p.hnizdo=$tisk_hnizdo ";
  $flds= explode(',',$fld);
  // číselníky                 akce.druh = ms_akce_typ:pečovatelé=7,kurz=1
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // data akce
  $rel= '';
  $rel= "-YEAR(narozeni)";
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $n= 0;
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,
            IFNULL(r2.spz,r1.spz) AS r_spz,
            IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
            IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
            IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
            IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
            IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefon,
            IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS email,
            o.id_osoba,s.skupinka as skupinka,s.pfunkce,
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
          JOIN pobyt AS xp ON xp.id_pobyt=xs.id_pobyt -- AND xp.funkce=99
          JOIN akce  AS xa ON xa.id_duakce=xp.id_akce AND YEAR(xa.datum_od)<=YEAR(a.datum_od)
          -- r1=rodina, kde je dítětem
          LEFT JOIN ( SELECT id_osoba,role,$r_fld
            FROM tvori JOIN rodina USING(id_rodina))
            AS r1 ON r1.id_osoba=o.id_osoba AND r1.role NOT IN ('a','b')
          -- r2=rodina, kde je rodičem
          LEFT JOIN ( SELECT id_osoba,role,$r_fld
            FROM tvori JOIN rodina USING(id_rodina))
            AS r2 ON r2.id_osoba=o.id_osoba AND r2.role IN ('a','b')
          WHERE (p.funkce=99 OR (p.funkce NOT IN (9,10,13,14,15,99) AND s.pfunkce IN (4,5,8))) 
            AND p.id_akce='$akce' AND $cnd
          GROUP BY id_osoba
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      // sumáře
      switch ($f) {
      case 'pfunkce':   $clmn[$n][$f]= $pfunkce[$x->$f]; break;
//      case '_pp':       $clmn[$n][$f]= $x->pfunkce==4 ? 1 : 0; break;
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
# ========================================================================================> . strava
# výpočet počtu strav podle aktuálních stravenek
# ---------------------------------------------------------------------------------- akce2 stravenky
function akce2_strava($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { //trace();
  global $diety,$diety_,$jidlo_;
  $dny= array('ne','po','út','st','čt','pá','so');
  $jidlo= array();
  $datum_od= select('datum_od','akce',"id_duakce=$akce");
  $den1= sql2stamp($datum_od);             // začátek akce ve formátu mktime
  $vylet= select1('DATE_FORMAT(ADDDATE(datum_od,2),"%e/%c")','akce',"id_duakce=$akce");
//   $diety= array('','_bm','_bl');                             -- globální nastavení
  foreach ($diety as $d) {
    foreach (array('vj','vjp') as $par_typ) {
    // sběr počtu jídel pro konkrétní dietu (normální strava=dieta 0)
      $par->dieta= $d;
      $par->typ= $par_typ;
      $res= akce2_stravenky_diety($akce,$par,"$title {$diety_[$d]}","$vypis$d",$export,$hnizdo,$id_pobyt);
      foreach ($res->tab_i as $den) {
        foreach ($den as $datum=>$jidla) {
          foreach ($jidla as $sov=>$cp) {
            foreach ($cp as $x=>$pocet) {
              if (!isset($jidlo[$datum][$d][$sov][$x])) $jidlo[$datum][$d][$sov][$x]= 0;
              $jidlo[$datum][$d][$sov][$x]+= $pocet;
            }
          }
        }
      }
    }
  }
  ksort($jidlo);
  $days= array_keys($jidlo);
  $days_fmt= array();
  // redakce
  $result= (object)array('html'=>'','href'=>'');
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  $flds= array();

  $tits[0]= "den:15";
  $flds[0]= "day";
  foreach (explode(',','s,o,v') as $jidlo1) {
    foreach ($diety as $dieta) {
      foreach (explode(',','c,p') as $porce) {
        $jidlox= $jidlo1.$porce;
        $tits[]= "{$jidlo_[$jidlox]} {$diety_[$dieta]}:8:r:s";
        $flds[]= "$jidlox $dieta";
      }
    }
  }
//                                                         debug($tits);
//                                                         debug($flds);
//                                                         debug($jidlo,'suma');
  // součet přes lidi
//  goto end;
  $d= 0;
  $ths= $tab= $href= '';
  foreach ($days as $day) {
    $d++;
    $mkden= mktime(0, 0, 0, date("n", $den1), date("j", $den1)+$day, date("Y", $den1));
    $po_ne= "{$dny[date('w',$mkden)]} ";
    $den= date("j/n",$mkden);
    $days_fmt[$day]= $den;
//    display("$mkden==$vylet");
    $clmn[$den]['day']= $po_ne . $den . ($den==$vylet ? " odečíst výlet" : '');
    foreach (explode(',','s,o,v') as $jidlo1) {
      foreach ($diety as $dieta) {
        foreach (explode(',','c,p') as $porce) {
          $jidlox= $jidlo1.$porce;
          $fld= "$den $jidlox $dieta";
          $fld2= "$den $jidlox$dieta";
          $sum= isset($jidlo[$day][$dieta][$jidlo1][$porce]) ? $jidlo[$day][$dieta][$jidlo1][$porce] : 0;
          $clmn[$den][$fld]= $sum;
          $suma[$fld2]+= $sum;
        }
      }
    }
  }
//                                                         debug($clmn,'clmn');
//  goto end;
  // zobrazení a export
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->suma= $suma;
    $result->days= $days_fmt;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $c) {
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
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
  }
end:  
  $result->html.= "<h3>Souhrn strav podle dnů, rozdělený podle typů stravy vč. diet</h3>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->href= $href;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 stravenky
# generování stravenek účastníky $akce - rodinu ($par->typ=='vj') resp. pečouny ($par->typ=='vjp')
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz, zena a děti
# generované vzorce
#   platit = součet předepsaných plateb
# výstupy
#   note = pro pečouny seznam jmen, pro které nejsou stravenky, protože nemají funkci
#          (tzn. asi nejsou na celý pobyt)
function akce2_stravenky($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { trace();
                                      debug($par,"akce2_stravenky($akce,...,$title,$vypis,$export,$hnizdo,$id_pobyt)");
  global $diety,$diety_,$jidlo_;
  $res_all= (object)array('res'=>array(),'html'=>'','jidel'=>array(),'max_jidel'=>0);
//   $diety= array('','_bm','_bl');                             -- globální nastavení
  foreach ($diety as $i=>$d) {
    // generování stravenek pro konkrétní dietu (normální strava=dieta 0)
    $par->dieta= $d;
    $res= akce2_stravenky_diety($akce,$par,"$title {$diety_[$d]}","$vypis$d",$export,$hnizdo,$id_pobyt);
    $res->dieta= $d;
    $res->nazev_diety= $diety_[$d];
    $res_all->res[]= $res;
    $res_all->html.= "<h3>Strava {$diety_[$d]}</h3>";
    $res_all->html.= $res->html;
    $res_all->html.= $res->note;
    // celkový počet jídel bez ohledu na dietu
    $res_all->max_jidel= max($res_all->max_jidel,$res->max_jidel);
    if (count($res_all->jidel)) {
      foreach (array_keys($res_all->jidel) as $jidlo) {
        $res_all->jidel[$jidlo]+= $res->jidel[$jidlo];
      }
    }
    else $res_all->jidel= $res->jidel;
  }
                                                debug($res_all->jidel,"celkem jídel - maximum=$res_all->max_jidel");
  return $res_all;
}
# ---------------------------------------------------------------------------- akce2 stravenky_diety
# bezmasá dieta na Pavlákové od roku 2017 nevaří
# proto se u pečounů mapuje bezmasá dieta na normální
function akce2_stravenky_diety($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { //trace();
//                                 debug($par,"akce_stravenky_diety($akce,,$title,$vypis,$export)");
  global $diety,$diety_,$jidlo_;  // $diety= array(''/*,'_bm'*/,'_bl')
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $jidel= array('sc'=>0,'sp'=>0,'oc'=>0,'op'=>0,'vc'=>0,'vp'=>0,);
  $cnd= $par->cnd;
  if ( $hnizdo ) $cnd.= " AND IF(funkce=99,s_hnizdo=$hnizdo,hnizdo=$hnizdo)";
  $dieta= $par->dieta;
  $note= $delnote= $html= $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= $par->typ=='vjp' ? "Pečovatel:25" : "Manželé:25";
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= pdo_qry($qrya);
  if ( $resa && ($a= pdo_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    $den1= sql2stamp($a->datum_od);             // začátek akce ve formátu mktime
    for ($i= 0; $i<=$nd; $i++) {
      $deni= mktime(0, 0, 0, date("n", $den1), date("j", $den1) + $i, date("Y", $den1));
      $den= $dny[($a->_den1+$i)%7].date('d',$deni).' ';
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
  $cond= $id_pobyt ? "p.id_pobyt=$id_pobyt" : $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $akce_data= (object)array();
  $dny= array('ne','po','út','st','čt','pá','so');
  if ( $par->typ=='vjp' ) { // pečouni
    switch ($dieta) {
    case '':
      $AND= "AND o.dieta!=1";
      break;
    case '_bm':
      $AND= "AND o.dieta=4";
//       fce_error("nepodporovaná dieta");
      break;
    case '_bl':
      $AND= "AND o.dieta=1";
      break;
    default:
      fce_error("nepodporovaná dieta");
    }
    $qry="SELECT o.prijmeni,o.jmeno,s.pfunkce,YEAR(datum_od) AS _rok,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto,
            p_od_pobyt, p_od_strava, p_do_pobyt, p_do_strava
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.id_akce='$akce' AND $cond AND p.funkce=99 AND s_rodici=0 AND p_kc_strava=0 $AND
            -- AND id_spolu IN (137569,137010)
          ORDER BY o.prijmeni,o.jmeno";
  }
  else { // rodiny
    $qry="SELECT /*akce2_stravenky_diety*/ strava_cel$dieta AS strava_cel,strava_pol$dieta AS strava_pol,
            cstrava_cel$dieta AS cstrava_cel,cstrava_pol$dieta AS cstrava_pol,
            p.pouze,
            IF(p.i0_rodina,CONCAT(r.nazev,' ',
              GROUP_CONCAT(po.jmeno ORDER BY role SEPARATOR ' a '))
             ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',pso.jmeno)
               ORDER BY role SEPARATOR ' a ')) as _jm,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto,
            a.hnizda AS akce_hnizda
          FROM pobyt AS p
          JOIN akce AS a ON p.id_akce=a.id_duakce
          LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
          JOIN spolu AS ps ON ps.id_pobyt=p.id_pobyt
          LEFT JOIN tvori AS pt ON pt.id_rodina=p.i0_rodina
            AND role IN ('a','b') AND ps.id_osoba=pt.id_osoba
          LEFT JOIN osoba AS po ON po.id_osoba=pt.id_osoba
          JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba
          WHERE p.id_akce='$akce' AND $cond AND p.funkce NOT IN (9,10,13,14,15)
          GROUP BY p.id_pobyt
          ORDER BY _jm";
  }
//   $qry.=  " LIMIT 1";
  $res= pdo_qry($qry);
  // stravenky - počty po dnech
  $str= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=datum,jídlo=s|o|v, typ=c|p
  $str_i= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=pořadí,jídlo=s|o|v, typ=c|p
  // s uvážením $oo='sv' - první jídlo prvního dne a poslední jídlo posledního dne
  $jidlo= array('s','o','v');
  $xjidlo= array('s'=>0,'o'=>1,'v'=>2);
  $jidlo_1= $xjidlo[$oo[0]];
  $jidlo_n= $xjidlo[$oo[1]];
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    if ( $par->typ=='vjp' && $x->pfunkce==0 && $x->_rok>2012 ) {        // !!!!!!!!!!!!!! od roku 2013
      $note.= "$delnote{$x->prijmeni} {$x->jmeno}";
      $delnote= ", ";
      continue;
    }
    $n++;
    $akce_data->nazev= $x->akce_nazev;
    $akce_data->rok=   $x->akce_rok;
    $akce_data->misto= $x->akce_misto;
    $akce_data->hnizda= $x->akce_hnizda;
    $str_kdo= array();
    $str_kdo_i= array();
    $clmn[$n]= array();
    $stravnik= $par->typ=='vjp' ? "{$x->prijmeni} {$x->jmeno}" : $x->_jm;
    $clmn[$n]['manzele']= $stravnik;
    // stravy
    if ( $par->typ=='vjp' ) { // pečoun => podle p_od* a p_do* nastav csc a csp
      $sc= $sp= 0; $csp= ''; // nemůže být použito
      // vytvoření řetězce cstrava_cel pro danou dietu
      $csc= str_repeat('0',3*($nd+1));
      $od_pobyt= $x->p_od_pobyt;
      $od_strava= $x->p_od_strava ? $x->p_od_strava - 1 : $jidlo_1;
      $do_pobyt= $nd - $x->p_do_pobyt;
      $do_strava= $x->p_do_strava ? $x->p_do_strava - 1 : $jidlo_n;
      for ($i= 3*$od_pobyt+$od_strava; $i<=3*$do_pobyt+$do_strava; $i++) {
        $csc[$i]= '1';
      }
//      display("$stravnik '$csc'  $od_pobyt..$do_pobyt ($nd)"); debug($x);
    }
    else {
      $sc= $x->strava_cel;
      $sp= $x->strava_pol;
      $csc= $x->cstrava_cel;
      $csp= $x->cstrava_pol;
    }
    $k= 0;
    for ($i= 0; $i<=$nd; $i++) {
      // projdeme dny akce
      $str_den= array();
      $j0= $i==0 ? $jidlo_1 : 0;
      $jn= $i==$nd ? $jidlo_n : 2;
      if ( 0<=$j0 && $j0<=$jn && $jn<=2 ) {
        for ($j= $j0; $j<=$jn; $j++) {
          $str_cel= $csc ? $csc[3*$i+$j] : $sc;
          $str_pol= $csp ? $csp[3*$i+$j] : $sp;
          if ( $str_cel ) $str_den[$jidlo[$j]]['c']= $str_cel;
          if ( $str_pol ) $str_den[$jidlo[$j]]['p']= $str_pol;
        }
      }
      else
        fce_error("Tisk stravenek selhal: chybné meze stravování v nastavení akce: $j0-$jn");
      if ( $i>0 || $oo[0]=='s' ) {
        // snídaně od druhého dne pobytu nebo začíná-li pobyt snídaní
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        // obědy od druhého do předposledního dne akce nebo prvního či posledního dne
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        // večeře do předposledního dne akce nebo končí-li pobyt večeří
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
      }
      if ( count($str_den) ) {
        $mkden= mktime(0, 0, 0, date("n", $den1), date("j", $den1)+$i, date("Y", $den1));
        $den= "<b>{$dny[date('w',$mkden)]}</b> ".date("j.n",$mkden);
        $str_kdo[$den]= $str_den;
        $str_kdo_i[$i]= $str_den;
      }
    }
    $kdo= $stravnik;
    $str[$kdo]= $str_kdo;
    $str_i[$kdo]= $str_kdo_i;
  }
//                                                         debug($str,"stravenky");
//                                                         debug($suma,"sumy");
  // titulky
  $ths= $tab= '';
  foreach ($tits as $idw) {
    list($id)= explode(':',$idw);
    $ths.= "<th>$id</th>";
  }
  // data
  $radku= 0;
  foreach ($clmn as $i=>$c) {
    $pocet= 0;
    foreach ($c as $val) {
      if (!is_numeric($val)) continue;
      $pocet+= $val;
    }
    if ( $pocet ) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
      $radku++;
    }
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
    // celkový počet jídel
    $max_jidel= 0;
    foreach ($suma as $den_jidlo=>$pocet) {
      $jidlo= mb_substr($den_jidlo,4,2);
      $jidel[$jidlo]+= $pocet;
      $max_jidel= max($max_jidel,$pocet);
    }
  }
  $result->html= "Seznam má $radku řádků<br><br>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->html.= "</br>";
  $result->href= $href;
  $result->jidel= $jidel; // celkový počet jídel
  $result->max_jidel= $max_jidel; // celkový počet jídel
  $result->tab= $str;
  $result->tab_i= $str_i;
  $result->akce= $akce_data;
  $result->note= $note ? "(bez $note, kteří nemají vyjasněnou funkci)" : '';
//  $result->suma= $suma;
//                                                      debug($jidel,"celkem jídel - max = $max_jidel - $dieta");
  return $result;
}
# ------------------------------------------------------------------------------ akce2 strava_souhrn
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function __akce2_strava_souhrn($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { trace();
  global $diety,$diety_,$jidlo_;
//                                                                 debug($par,"akce2_strava_souhrn");
  $result= (object)array();
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  $flds= array();

  $par->souhrn= 1;
  $ret= akce2_strava_pary($akce,$par,$title,$vypis,$export,0);
  //
  $tits[0]= "den:10";
  $flds[0]= "day";
//   foreach (explode(',','sc,sp,oc,op,vc,vp') as $jidlo) {
  foreach (explode(',','s,o,v') as $jidlo1) {
    foreach ($diety as $dieta) {
  foreach (explode(',','c,p') as $porce) {
    $jidlo= $jidlo1.$porce;
      $tits[]= "{$jidlo_[$jidlo]} {$diety_[$dieta]}:8:r:s";
      $flds[]= "$jidlo $dieta";
    }
    }
  }
//                                                         debug($tits);
//                                                         debug($flds);
//                                                         debug($ret->suma,'suma');
  // součet přes lidi
  $d= 0;
  foreach ($ret->days as $day) {
    $d++;
    $clmn[$day]['day']= $day;
//     foreach (explode(',','sc,sp,oc,op,vc,vp') as $jidlo) {
//       foreach ($diety as $dieta) {
  foreach (explode(',','s,o,v') as $jidlo1) {
    foreach ($diety as $dieta) {
  foreach (explode(',','c,p') as $porce) {
    $jidlo= $jidlo1.$porce;
        $fld= "$day$jidlo $dieta";
        $clmn[$day][$fld]= $ret->suma[$fld];
      }
    }
    }
  }
//                                                         debug($clmn,'clmn');
  // zobrazení a export
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
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
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
  }
  $result->html.= "<h3>Souhrn strav podle dnů, rozdělený podle typů stravy vč. diet</h3>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->href= $href;
  return $result;
}
# ------------------------------------------------------------------------------- akce2 strava_vylet
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_strava_vylet($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { //trace();
  global $diety,$diety_,$jidlo_,$EZER, $tisk_hnizdo;
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
  $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, $i_spolu_note;
//                                                                 debug($par,"akce2_strava_souhrn");
  $result= (object)array('html'=>'');
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  $flds= array();

  // zjistíme datum dne výletu tj. třetího dne LK
  $vylet= select1('DATE_FORMAT(ADDDATE(datum_od,2),"%e/%c")','akce',"id_duakce=$akce");
  // projdeme páry s dětmi ve věku nad 3 roky a děti sečteme
  $pocet_deti= $pocet_cele= $pocet_polo= 0;
  $cnd= "p.funkce NOT IN (9,10,13,14,15,99) AND p.hnizdo=$tisk_hnizdo ";
  $browse_par= (object)array(
    'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",'having'=>'','order'=>'a _nazev',
    'sql'=>"SET @akce:=$akce,@soubeh:=0,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
  # rozbor výsledku browse/ask
  $sum= 0;
  $tab= '';
  array_shift($y->values);
  foreach ($y->values as $x) {
    // údaje pobytu $x->pobyt
    $xs= explode('≈',$x->r_cleni);
    $vek_deti= array();
    $deti_nad3= $cel= $pol= $chuv= 0;
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
      if ( $o[$i_key_spolu] && $x->key_rodina ) {
        if ( $o[$i_osoba_role]=='p' ) {
          $chuv++;
        }
        if ( $o[$i_osoba_role]=='d' ) {
          $vek= $o[$i_osoba_vek];
          if ( $vek<3 ) break;
          $vek_deti[]= $vek;
          $deti_nad3++;
          $pocet_deti++;
        }
      }
    }
    if ( !$deti_nad3 ) continue;
//     $test= array(48838,49080,48553);
//    $test= array(48673);
//     if ( in_array($x->key_pobyt,$test) ) {
//       $tab.= "<br>{$x->key_pobyt} {$x->_nazev} (děti nad 3 mají roků:".implode(',',$vek_deti).") ";
      $tab.= "<br>{$x->_nazev} (věk dětí starších 3 let: ".implode(',',$vek_deti).") ";
//                                                         debug($x);
//      $ret= akce2_strava_pary($akce,$par,$title,$vypis,$export,$x->key_pobyt);
      $ret= akce2_strava($akce,(object)array(),'',$vypis,true,0,$x->key_pobyt);
      foreach ($diety as $dieta) {
        $cel+= $ret->suma["$vylet oc$dieta"];
        $pol+= $ret->suma["$vylet op$dieta"];
//                                                         debug($ret->suma,"$vylet op$dieta = $pol");
      }
//       $tab.= "... cele=$cel polo=$pol";
      // odečteme stravu rodičů - asi cc cp pp
      if ( $cel+$pol >= 2+$chuv ) {
        // něco zůstane na děti
        if ( $cel>=2+$chuv ) { $cel-= 2+$chuv; }
        elseif ( $cel==1 ) { $cel--; $pol--; }
        else { $pol-= 2; }
      }
      $tab.= "... objednali asi cele=$cel polo=$pol";
      // pokud je víc strav jak děti3 tak asi je i to 3 leté
      $pod3= ($cel+$pol) - $deti_nad3;
      if ( $pod3 > 0 ) {
        // tak je odečteme ... spolehneme se, že namá celou
        if ( $pol >= $pod3 ) { $pol-= $pod3; }
        $tab.= "...oprava: cele=$cel polo=$pol  ($deti_nad3,$pod3)";
      }
//     }//test
    $pocet_cele+= $cel;
    $pocet_polo+= $pol;
  }
  $sum.= "<p> Dětí nad 3 roky je $pocet_deti ... mají  objednaných asi
    $pocet_cele celých obědů a $pocet_polo polovičních</p>";
end:
  $result->html.= "<h3>Odhad obědů objednaných pro děti nad 3 roky na den $vylet</h3>";
  $result->html.= "$sum<br><hr>protože si myslím, že <br>$tab";
  return $result;
}
# -------------------------------------------------------------------------------- akce2 strava_pary
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt = je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
#   $souhrn = indexy jsou upravené pro fci akce2_strava_souhrn
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function __akce2_strava_pary($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { //trace();
//                                                                 debug($par,"akce2_strava_pary");
  global $diety, $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $souhrn= $par->souhrn?:0;
  $result= (object)array();
  $cnd= 1;
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé a pečouni:25";  // bude opraveno podle skutečnosti před exportem
  $fld= "manzele";
  $dny= $souhrn ? array('ne','po','út','st','čt','pá','so') : array('n','p','ú','s','č','p','s');
  $days= array();       // pro souhrn
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= pdo_qry($qrya);
  if ( $resa && ($a= pdo_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    for ($i= 0; $i<=$nd; $i++) {
//       foreach ($diety as $dieta) {
        $den= $dny[($a->_den1+$i)%7].date($souhrn?' j/n':'d',sql2stamp($a->datum_od)+$i*60*60*24).' ';
        $den= date($souhrn?' j/n':'d',sql2stamp($a->datum_od)+$i*60*60*24).' ';
      foreach ($diety as $dieta) {
        if ( !$dieta ) $days[]= $den;
        if ( $i>0 || $oo[0]=='s' ) {
          $tit.= ",{$den}sc $dieta:4:r:s";
          $tit.= ",{$den}sp $dieta:4:r:s";
          $fld.= ",{$den}sc $dieta,{$den}sp $dieta";
        }
        }
      foreach ($diety as $dieta) {
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $tit.= ",{$den}oc $dieta:4:r:s";
          $tit.= ",{$den}op $dieta:4:r:s";
          $fld.= ",{$den}oc $dieta,{$den}op $dieta";
        }
        }
      foreach ($diety as $dieta) {
        if ( $i<$nd || $oo[1]=='v' ) {
          $tit.= ",{$den}vc $dieta:4:r:s";
          $tit.= ",{$den}vp $dieta:4:r:s";
          $fld.= ",{$den}vc $dieta,{$den}vp $dieta";
        }
        }
//       }
    }
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
//                                                         debug($flds);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
//                                                         debug($suma);
  // pokud není id_pobyt tak vyloučíme náhradníky + odhlášen + přihláška
  // naopak 'nepřijel' zahrneme (strava již byla objednána)
  $cond.= $id_pobyt ? " AND p.id_pobyt=$id_pobyt" : " AND funkce NOT IN (9,10,13,14,15)";
  $jsou_pecouni= false;
  // data akce
  $flds_diety= isset($diety['_bm'])
    ? "SUM(IF(pso.dieta=0,1,0)) AS _dieta,SUM(IF(pso.dieta=1,1,0)) AS _dieta_bl,SUM(IF(pso.dieta=4,1,0)) AS _dieta_bm"
    : "SUM(IF(pso.dieta!=1,1,0)) AS _dieta,SUM(IF(pso.dieta=1,1,0)) AS _dieta_bl,0 AS _dieta_bm";
  $res= tisk2_qry('pobyt_dospeli_ucastnici',
  /*flds*/  "strava_cel,strava_pol,cstrava_cel,cstrava_pol,
              strava_cel_bm,strava_pol_bm,cstrava_cel_bm,cstrava_pol_bm,
              strava_cel_bl,strava_pol_bl,cstrava_cel_bl,cstrava_pol_bl,
              p_od_pobyt, p_od_strava, p_do_pobyt, p_do_strava,
              COUNT(*) AS _pocet, $flds_diety,funkce,pfunkce",
  /*WHERE*/ "p.id_akce='$akce' AND IF(funkce=99,s_rodici=0 AND pfunkce,1) "
    . " AND funkce=99 AND id_spolu IN (137569)"
//    . " AND p.id_pobyt IN (45406,44921)"
//    . " AND p.id_pobyt IN (44921)"
   . " AND $cond $jen_hnizdo",
  /*HAVING*/  "",
  /*ORDER*/  "IF(funkce=99,'',pr.nazev)");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    if ( $x->funkce==99 && $x->pfunkce ) {
//                                                        debug($x,"hodnoty pečounů");
      // --------------------------------------------- stravy pečouni
      // počet se počítá podle atributu osoba.dieta s opravou podle poskytnutých diet
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        // stravy pro pečouny - mají jednotně celou stravu - (s_rodici=0,pfunkce!=0 viz SQL)
        // mají diety podle osobního nastavení diety: 0=, 1=_bl, 4=_bm
        // nemají poloviční
        $jsou_pecouni= true;
        $clmn[$n]['manzele']= 'PEČOUNI';
        if ( $i>0 || $oo[0]=='s' ) {
          // snídaně
          foreach ($diety as $dieta) {
            $sp= 0;
            $f= "_dieta$dieta"; $sc= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= 0;
          }
                                                        display("A $i $k");
        }
        if ( $i>0 && $i<$nd                                 // prostřední den
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')        // první začíná-li snídaní nebo obědem
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {    // poslední končí-li
          // obědy
          foreach ($diety as $dieta) {
            $sp= 0;
            $f= "_dieta$dieta"; $sc= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= 0;
          }
                                                        display("B $i $k");
        }
        if ( $i<$nd || $oo[1]=='v' ) {  // ne poslední den (pokud není s večeří)
          // večeře
          foreach ($diety as $dieta) {
            $sp= 0;
            $f= "_dieta$dieta"; $sc= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= 0;
          }
                                                        display("C $i $k");
        }
      }
    }
    elseif ( $x->funkce!=99 ) {
      // --------------------------------------------- stravy účastníci
      // počet se počítá podle atributu pobyt.strava_*
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
//         foreach ($diety as $dieta) {
          // stravy pro manžele podle diet
          $clmn[$n]['manzele']= $x->_jm;
          if ( $i>0 || $oo[0]=='s' ) {
        foreach ($diety as $dieta) {
          $f=  "strava_cel$dieta"; $sc= $x->$f;
          $f=  "strava_pol$dieta"; $sp= $x->$f;
          $f= "cstrava_cel$dieta"; $csc= $x->$f;
          $f= "cstrava_pol$dieta"; $csp= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
          }
          }
          if ( $i>0 && $i<$nd
            || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
            || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        foreach ($diety as $dieta) {
          $f=  "strava_cel$dieta"; $sc= $x->$f;
          $f=  "strava_pol$dieta"; $sp= $x->$f;
          $f= "cstrava_cel$dieta"; $csc= $x->$f;
          $f= "cstrava_pol$dieta"; $csp= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
          }
          }
          if ( $i<$nd || $oo[1]=='v' ) {
        foreach ($diety as $dieta) {
          $f=  "strava_cel$dieta"; $sc= $x->$f;
          $f=  "strava_pol$dieta"; $sp= $x->$f;
          $f= "cstrava_cel$dieta"; $csc= $x->$f;
          $f= "cstrava_pol$dieta"; $csp= $x->$f;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
            $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
          }
          }
        }
//                                                         debug($clmn,$x->_jm);
//       }
    }
  }
//                                                         debug($clmn,"clmn");
//                                                         debug($suma,"sumy");
  // souhrn
  if ( $souhrn ) {
    $result->days= $days;
    $result->suma= $suma;
  }
  // zobrazení a export
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
        $style= akce2_sestava_td_style($fmts[$id]);
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
//                                                         debug($result);
  return $result;
}
# ----------------------------------------------------- akce2 sestava_td_style
# $fmt= r|d
function akce2_sestava_td_style($fmt) {
  $style= array();
  switch ($fmt) {
  case 'r': $style[]= 'text-align:right'; break;
  case 'd': $style[]= 'text-align:right'; break;
  case '!': $style[]= 'color:red'; break;
  }
  return count($style)
    ? " style='".implode(';',$style)."'" : '';
}
# ======================================================================================> . přehledy
# ---------------------------------------------------------------------------------- akce2 prihlasky
# přehled přihlášek na akci
function akce2_prihlasky($akce,$par,$title,$vypis,$export=false) { 
  global $EZER;
  $res= (object)array('html'=>'');
  
  $limit= ''; // "LIMIT 1";
  $po= isset($par->po) ? $par->po : 7;
  $tydnech= "týdnech";
  $tydnu= "týdnu";
  $tyden= "týden";
  if ($po==30) {
    $tydnech= "měsících";
    $tydnu= "měsíci";
    $tyden= "měsíc";
  }
  $dny_a= $dny_b= $dny_x= array(); 
  $max= 0;
  $pob= 0;
  $qp=  "SELECT id_pobyt,funkce,
       (SELECT DATEDIFF(datum_od,kdy) FROM _track 
        WHERE kde='pobyt' AND klic=id_pobyt ORDER BY id_track LIMIT 1) AS kde
    FROM pobyt JOIN akce ON id_akce=id_duakce
    WHERE id_akce='$akce' AND funkce!=99 $limit ";
  $rp= pdo_qry($qp);
  while ( $rp && (list($idp,$fce,$dif)= pdo_fetch_row($rp)) ) {
    $pob++;
    $max= max($dif,$max);
    if ($fce==1) {
      if (!isset($dny_a[$dif])) $dny_a[$dif]= 0;
      $dny_a[$dif]++;
    }
    elseif (in_array($fce,array(9,10,13,14,15))) {
      if (!isset($dny_x[$dif])) $dny_x[$dif]= 0;
      $dny_x[$dif]++;
    }
    else {
      if (!isset($dny_b[$dif])) $dny_b[$dif]= 0;
      $dny_b[$dif]++;
    }
  }
  ksort($dny_a);
  ksort($dny_b);
  ksort($dny_x);
  // zhuštění výsledku
  $na= $nb= $nx= 0;
  $hist_a= $hist_b= $hist_x= array();
  $last_h= -1;
  for ($d= 0; $d<=$max; $d++) {
    $ya= isset($dny_a[$d]) ? $dny_a[$d] : 0;
    $yb= isset($dny_b[$d]) ? $dny_b[$d] : 0;
    $yx= isset($dny_x[$d]) ? $dny_x[$d] : 0;
    $na+= $ya;
    $nb+= $yb;
    $nx+= $yx;
    $h= floor($d/$po);
//    display("$d / $po = $h");
    if ($h==$last_h) {
      $hist_a[$h]+= $ya;
      $hist_b[$h]+= $yb;
      $hist_x[$h]+= $yx;
    }
    else {
      $last_h= $h;
      $hist_a[$h]= $ya;
      $hist_b[$h]= $yb;
      $hist_x[$h]= $yx;
    }
  }
  // integrál
  $hist_z= array(0,0);
  $hist[count($hist_a)]= 0;
  for ($h= count($hist_a)-1; $h>=0; $h--) {
    $hist_z[$h]+= $hist_z[$h+1]+$hist_a[$h]+$hist_b[$h]+$hist_x[$h];
  }
    /**/                                                 debug($hist_z,'integrál');
//    /**/                                                 debug($hist_a,'funkce');
//    /**/                                                 debug($hist_b,'bez funkce');
//    /**/                                                 debug($dny_a,'funkce');
//    /**/                                                 debug($dny_b,'bez funkce');
  
  // výsledek
  $res->html= "<h3>Přehled data zápisu $pob přihlášek na akci</h3>
      <i>přehled se zobrazuje podle <u>dne zapsání</u> přihlášky v součtu po $tydnech, vlevo je $tyden konání akce
      <br>zeleně jsou účastníci bez funkce ($nb), oranžově jsou VPS $na, černě jsou ti, co na akci nakonec nebyli ($nx)
      </i><br><br>";
  $x= $y= $z= '';
  $ratio= 5;
  for ($h= 1; $h<count($hist_a); $h++) {
    $xx= $h<10 ? "0$h" : $h;
    $ya= isset($hist_a[$h]) ? $hist_a[$h] : 0;
    $yb= isset($hist_b[$h]) ? $hist_b[$h] : 0;
    $yx= isset($hist_x[$h]) ? $hist_x[$h] : 0;
    $pocty= "$yb<br>$yx<br>$ya";
    $ya*= $ratio;
    $yb*= $ratio;
    $yx*= $ratio;
    $img= "<div class='curr_akce' style='height:{$yb}px;width:12px;margin-top:5px'></div>";
    $img.= "<div style='background:black;height:{$yx}px;width:12px;margin-top:5px'></div>";
    $img.= "<div class='parm' style='height:{$ya}px;width:12px;margin-top:5px'></div>";
    $x.= "<td>$xx</td>";
    $y.= "<td style='vertical-align:bottom'>$pocty $img </td>";
    $z.= "<td>{$hist_z[$h]}</td>";
  }
  $res->html.= "<table>
    <tr><td align='right' style='vertical-align:bottom'>v $tydnu zapsáno:<br></td>$y</tr>
    <tr><td align='right'>n-tý $tyden před akcí:</td>$x</tr>
    <tr><td align='right'>celkem přihlášek:</td>$z</tr></table>";
  return $res;
}
# ------------------------------------------------------------------------------------- akce2 pokoje
# odhad počtu potřebných pokojů, založený na následujících úvahách
#  - rodiny tj. "pobyty" spolu nesdílí pokoje
#  - dítě do 3 let spí ve své postýlce v pokoji rodičů
#  - dvě děti mezi 3-6 lety spolu mohou sdílet jednu dospělou postel 
#    $par->max_vek_spolu přitom určuje maximální věk dítětě, které se snese s mladším na lůžku
function akce2_pokoje($akce,$par,$title,$vypis,$export=false) { 
  global $EZER;
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, 
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta;
//                                            debug($par,"akce2_pokoje");
  $max_vek_spolu= isset($par->max_vek_spolu) ? $par->max_vek_spolu : 0;
  $res= (object)array('html'=>'');
  $org= select1("access","akce","id_duakce=$akce");
  $c_dite_lcd= $org==2
      ? map_cis('fa_akce_dite_kat','barva') 
      : map_cis('ys_akce_dite_kat','barva');  
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  list($hlavni,$soubezna)= select("a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$akce");
  $soubeh= $soubezna ? 1 : ( $hlavni ? 2 : 0);
  $browse_par= (object)array(
    'cmd'=>'browse_load',
    'cond'=>"p.id_akce=$akce AND p.funkce NOT IN (9,10,13,14,15)",  // jen přítomní
//    'having'=>$hav,
    'order'=>'a__nazev',
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
//  /**/                                                   debug($y);
  # rozbor výsledku browse/ask
  array_shift($y->values);
  $pokoj= array();
  $celkem= 0;
  foreach ($y->values as $x) {
    $xs= explode('≈',$x->r_cleni);
//    /**/                                                 debug($x);
    $pocet= 0;
    $deti= array();
    $male= 0;
    $celkem++;
//                                                         if ( $x->key_pobyt==32146 ) debug($x);
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
//    /**/                                                 debug($o);
//                                                         if ( $x->key_pobyt==32146 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $pocet++;
        if ( $o[$i_osoba_role]=='d' ) {
          $deti[$i]['jmeno']= $o[$i_osoba_jmeno];
          $vek= $deti[$i]['vek']= $o[$i_osoba_vek];
          $deti[$i]['lcd']= $c_dite_lcd[$o[$i_spolu_dite_kat]]; 
          // dítě do 3 let nepotřebuje postel
          if ($vek<3) 
            $pocet--;
          elseif ($vek<$max_vek_spolu) {
            $male++;
          }
        }
      }
    }
//    /**/                                                 debug($deti,"DĚTI, $male");
    // rozbor případů
    $posteli= $pocet;
    if ($male>=2) $posteli--;
    /**/                                                 display("$posteli - $x->_nazev");
    if (!isset($pokoj[$posteli])) $pokoj[$posteli]= 0;
    $pokoj[$posteli]++;
  }
  // výsledek
  $sdileni= $max_vek_spolu 
      ? "<li><i>dvě děti mezi 3 a $max_vek_spolu lety budou sdílet dospělé lůžko (ale ne vždy to jde)</i>" 
      : '';
  $res->html.= "<h3>Hrubý odhad počtu pokojů pro akci</h3>
    Za předpokladu, že:<ul>
    <li> rodiny nesdílí pokoje (někdy ale starší děti ze spřátelených rodin chtějí)
    <li> dítě do 3 let spí v dovezené postýlce s rodiči
    $sdileni
    </ul>
    tak potřebujeme celkem <b>$celkem</b> pokojů v těchto velikostech:<br><br><table>
    ";
  ksort($pokoj);
  foreach ($pokoj as $posteli=>$pocet) {
    $res->html.= "<tr><td align='right' style='width:30px'><b>$pocet</b></td>
      <td>&nbsp;&nbsp;&nbsp;$posteli-lůžkových pokojů</td></tr>";
  }
  $res->html.= "</table><br>Pochopitelně např. 5-lůžkový pokoj lze nahradit dvěma menšími ";
    /**/                                                 debug($pokoj,"pokoje");
  return $res;
}
# ------------------------------------------------------------------------------ akce2 cerstve_zmeny
# generování seznamu změn v pobytech na akci od par-datetime
function akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export=false) { 
//                                            debug($par,"akce2_cerstve_zmeny");
  $result= (object)array('html'=>'');
//  $od= $par->datetime= "2013-09-25 18:00";
  $od= '';
  if ( $par->zmeny ) {
    $delta= strtotime("$par->zmeny hours ago");
    $od= date("Y-m-d H:i",$delta);
  }
//  $p_flds= "'luzka','pokoj','pristylky','pocetdnu'";
  $par->fld= 'luzka,kocarek,pokoj,budova,pristylky,pocetdnu';
  $p_flds= array();
  foreach (explode(',',$par->fld) as $fld) { $p_flds[]= "'$fld'"; }
  $p_flds= implode(',',$p_flds);
  $p_tabs= array();
  foreach (explode(',',$par->tab) as $tab) { $p_tabs[]= "'$tab'"; }
  $p_tabs= implode(',',$p_tabs);
  //
  $tits= explode(',',"_ucastnik,kdy,fld,old,val,kdo,id_track");
  $flds= $tits;
  $clmn= array();
  $n= 0;
  $ord= "IF(i0_rodina,r.nazev,o.prijmeni)";
//  $ord= "
//    CASE
//      WHEN pouze=0 THEN r.nazev
//      WHEN pouze=1 THEN GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '')
//      WHEN pouze=2 THEN GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '')
//    END";
  $AND_fld_in= $par->tab=='pobyt' ? "AND fld IN ($p_flds)" : "AND fld NOT IN ($p_flds)";
  $JOIN= $par->tab=='pobyt' ? "
    JOIN pobyt AS p ON p.id_pobyt=klic
    JOIN akce AS a ON a.id_duakce=p.id_akce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=r.id_rodina
    " : "
    ";
  $rz= pdo_qry("
    SELECT id_track,kdy,kdo,klic,fld,op,old,val,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
      p.i0_rodina,r.nazev as nazev,o.prijmeni,o.jmeno
    FROM _track $JOIN
    WHERE kde IN ($p_tabs) AND kdy>='$od' AND id_akce=$akce $AND_fld_in AND old!=val -- AND old!=''
      AND NOT ISNULL(p.id_pobyt)
    GROUP BY id_track,id_pobyt
--  ORDER BY $ord,kdy
    ORDER BY kdy DESC
  ");
  while ( $rz && ($z= pdo_fetch_object($rz)) ) {
    $nazev= $z->i0_rodina ? "$z->nazev $z->jmeno_m a $z->jmeno_z" : "$z->prijmeni $z->jmeno";
//    $prijmeni= $z->pouze==1 ? $z->prijmeni_m : ($z->pouze==2 ? $z->prijmeni_z : $z->nazev);
//    $jmena=    $z->pouze==1 ? $z->jmeno_m    : ($z->pouze==2 ? $z->jmeno_z : "{$z->jmeno_m} a {$z->jmeno_z}");
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case '_ucastnik': $clmn[$n][$f]= $nazev; break; // "$prijmeni $jmena"; break;
      default:          $clmn[$n][$f]= $z->$f;
      }
    }
    $n++;
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  $bylo= $od ? "Od $od (podle nastavení na kartě Účastníci/obarvit změněné údaje) bylo " : "Bylo";
//  $kde= $p_tabs=='pobyt'
  $nadpis= "$bylo provedeno $n změn ... nahoře jsou nejnovější
    <hr>sledují se změny jen v položkách: <b>$par->fld</b>
    ... podle potřeby to lze změnit - řekni Martinovi<br><br>
    ";
//  $nadpis= "$bylo provedeno $n změn
//    <hr>sledují se jen <b>změny</b> (tzn. nikoliv nově zadané hodnoty) v položkách: $par->fld
//    <br>.. podle přání lze toto změnit - řekni Martinovi<br><br>
//    ";
  $result= tisk2_table($tits,$flds,$clmn,$export,$nadpis);
  return $result;
}
# ----------------------------------------------------------------------------------- akce2 text_eko
function akce2_text_eko($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $result= (object)array();
  $html= '';
  // zjištění, zda má akce nastavený ceník
  if (!select('COUNT(*)','cenik',"id_akce=$akce")) { 
    $html= "Tato akce nemá nastavený ceník, ekonomické ukazatele nelze tedy spočítat";
    goto end;
  }
  $prijmy= 0;
  $vydaje= 0;
  $pary= 0;
  $prijem= array();
  // zjištění mimořádných pečovatelů
  $qm="SELECT id_spolu FROM pobyt AS p  JOIN akce  AS a ON p.id_akce=a.id_duakce
      JOIN spolu AS s USING(id_pobyt) WHERE p.id_akce='$akce' AND p.funkce=99 AND s.pfunkce=6 ";
  $rm= pdo_qry($qm);
  $n_mimoradni= pdo_num_rows($rm);
//   $mimoradni= $n_mimoradni ? "platba za stravu a ubytování $n_mimoradni mimořádných pečovatelů, kterou uhradili" : '';

  // -------------------------------------------- příjmy od účastníků na pečouny
//                                                        display("příjmy od účastníků");
  $test_n= $test_kc= 0;
  $limit= '';
//   $limit= "AND id_pobyt IN (17957,18258,18382)";
  $qp=  "SELECT id_pobyt,funkce FROM pobyt WHERE id_akce='$akce' AND funkce IN (0,1,2,5,6) $limit ";
  $rp= pdo_qry($qp);
  while ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $pary++;
    $ret= akce2_vzorec($p->id_pobyt); // bere do úvahy hnízda
    if ( $ret->err ) { $html= $ret->err; goto end; }
//                                                         if ($ret->eko->slevy) {
//                                                         debug($ret->eko->slevy,"sleva pro fce={$p->funkce}");
//                                                         goto end; }
    if ( $ret->eko->vzorec ) {
//                                                         debug($ret->eko,"vzorec {$p->id_pobyt}");
      foreach ($ret->eko->vzorec as $x=>$kc) {
        if ( !isset($prijem[$x]) ) $prijem[$x]= (object)array();
        $prijem[$x]->vzorec+= $kc;
        $prijem[$x]->pocet++;
        $corr= false;
        $slevy= $ret->eko->slevy;
        if ( $slevy ) {
          if ( $slevy->procenta ) {
            $prijem[$x]->platba+= round(($kc * (100 - $slevy->procenta)/100),-1);
            $corr= true;
          }
        }
        if ( !$corr ) {
          $prijem[$x]->platba+= $kc;
        }
      }
    }
  }
//  /**/                                                  debug($prijem,"prijem");
  // zobrazení příjmů
  $rows_vydaje= '';
  $rows_prijmy= '';
  $qc= "SELECT GROUP_CONCAT(DISTINCT polozka) AS polozky, za
        FROM cenik
        WHERE id_akce='$akce' AND za!=''
        GROUP BY za ORDER BY poradi ASC";
  $rc= pdo_qry($qc);
  while ( $rc && ($c= pdo_fetch_object($rc)) ) {
    if ( $prijem[$c->za]->vzorec ) {
      $cena= $platba= '';
      if ( $prijem[$c->za]->vzorec ) $cena= $prijem[$c->za]->vzorec;
      if ( $prijem[$c->za]->platba ) $platba= $prijem[$c->za]->platba;
      $_cena= number_format($cena, 0, '.', ' ');
      $platba= number_format($platba, 0, '.', ' ');
//       $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td><td align='right'>$platba</td></tr>";
      $rows_prijmy.= "<tr><td>{$c->polozky}</td><td align='right'>$_cena</td></tr>";
      if ( $c->za=='P' ) {
        $solid= $pary*200;
        $prijmy+= $solid;
        $_solid= number_format($solid, 0, '.', ' ');
        $rows_prijmy.= "<tr><td>... z toho solidárně po 100Kč na děti</td><td align='right'>$_solid</td></tr>";
      }
      else 
        $prijmy+= $cena;
    }
  }
//  /**/                                                    display("cena=$cena, platba=$platba");
//  /**/                                                    display("výdaj za pečouny");
  // --------------------------------------- náklad na stravu pečounů
  // nově podle počtu vydaných stravenek BEZ HNIZD!!
  $hnizda= select('hnizda','akce',"id_duakce=$akce");    
  if ($hnizda) fce_error("pro hnízda odhad nefunguje");
  $hnizdici= 0;
  $vydaje= $radni_vydaje= $mimoradni_vydaje= 0;
  $rows_vydaje= '';
  $radni= akce2_stravenky($akce,(object)array('typ'=>'vjp','cnd'=>'pfunkce!=6','zmeny'=>0),'','');
  $max_radni= $radni->max_jidel;
  $ra= pdo_qry("SELECT za,cena FROM cenik "
      . "WHERE id_akce=$akce AND za IN ('sc','sp','oc','op','vc','vp')");
  $mimoradni= akce2_stravenky($akce,(object)array('typ'=>'vjp','cnd'=>'pfunkce=6','zmeny'=>0),'','');
  $max_mimoradni= $mimoradni->max_jidel;
  $ra= pdo_qry("SELECT za,cena FROM cenik "
      . "WHERE id_akce=$akce AND za IN ('sc','sp','oc','op','vc','vp')");
  while ( $ra && (list($za,$cena)= pdo_fetch_array($ra)) ) {
    $pocet= $radni->jidel[$za];
    $radni_vydaje+= $pocet*$cena;
    $pocet= $mimoradni->jidel[$za];
    $mimoradni_vydaje+= $pocet*$cena;
  }
  $vydaje+= $radni_vydaje+$mimoradni_vydaje;
  $vydaje_f= number_format($vydaje, 0, '.', ' ');
  $radni_vydaje_f= number_format($radni_vydaje, 0, '.', ' ');
  $mimoradni_vydaje_f= number_format($mimoradni_vydaje, 0, '.', ' ');
  $prijmy+= $mimoradni_vydaje;
  $rows_vydaje.= "<tr><td>stravenky řádní pečovatelé (max. současně $max_radni)</td>"
      . "<td align='right'>$radni_vydaje_f</td></tr>";
  $rows_vydaje.= "<tr><td>stravenky mimořádní pečovatelé (max. současně $max_mimoradni)</td>"
      . "<td align='right'>$mimoradni_vydaje_f</td></tr>";
  $rows_vydaje.= "<tr><td>celkem</td><td align='right'>$vydaje_f</td></tr>";
  $rows_prijmy.= "<tr><td>stravenky $n_mimoradni mimořádní peč.</td><td align='right'>$mimoradni_vydaje_f</td></tr>";
  $pecounu= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
      "id_akce='$akce' AND funkce=99 AND s_rodici=0");
  
/*  
  // postaru - kteří mají funkci a nemají zaškrtnuto "platí rodiče"
  // podle hnízd nebo celkově
  $pecounu= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
      "id_akce='$akce' AND funkce=99 AND s_rodici=0");
  $hnizda= select('hnizda','akce',"id_duakce=$akce");    
  $pecouni= array();
  $hnizdici= 0;
  $vydaje= 0;
  if ($hnizda) {
    foreach (explode(',',$hnizda) AS $i=>$h) {
      $ih= $i+1;
      $nh= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
              "id_akce='$akce' AND funkce=99 AND s_hnizdo=$ih AND s_rodici=0");
      $vzorec= akce2_vzorec_test($akce,$ih,0,0,0,0,$nh,'stat');
      $pecouni[]= (object)array('nazev'=>trim($h),'pocet'=>$nh,html=>$vzorec->navrh);
      $hnizdici+= $nh;
      $vydaje+= $vzorec->cena;
    }
  }
  else {
    $vzorec= akce2_vzorec_test($akce,0,0,0,0,0,$pecounu,'stat');
    $pecouni[]= (object)array('nazev'=>'','pocet'=>$pecounu,'html'=>$vzorec->navrh);
    $vydaje= $vzorec->cena;
  }
*/
//  /**/                                                  debug($pecouni,"celkem $pecounu/$hnizdici");
  // odhad příjmů za mimořádné pečouny - přičtení k příjmům
//  if ( $n_mimoradni ) {
//    $cena_mimoradni= $vydaje*$n_mimoradni/$pecounu;
//    $prijmy+= $cena_mimoradni;
//    $cena= number_format($cena_mimoradni, 0, '.', ' ');
//    $rows_prijmy.= "<tr><td>ubytování a strava $n_mimoradni mimoř.peč.</td>
//      <td align='right'>$cena</td></tr>";
//  }
  // formátování odpovědi dle ceníku akce
  $h= $tisk_hnizdo ? "(souhrně za všechna hnízda)" : '';
  $html.= "<h3>Příjmy za akci $h podle aktuální skladby účastníků</h3>";
  $html.= "Pozn. pokud jsou někteří pečovatelé tzv. mimořádní, předpokládá se, že jejich pobyt 
    <br>je uhrazen mimo pečovatelský rozpočet.<br>";
//   $html.= "Pozn. pro přehled se počítá také cena s uplatněnou procentní slevou (např. VPS)<br>";
//   $html.= "(příjmy pro pečovatele se počítají z plné tzn. vyšší ceny)<br>";
//   $html.= "<br><table class='stat'><td>položky</td><th>cena bez slev</th><th>cena po slevě</th></tr>";
  $html.= "<br><table class='stat'>";
//  $html.= "<tr><td>položky</td><th>cena</th></tr>";
  $html.= "$rows_prijmy</table>";
  $html.= "<h3>Výdaje za stravu pro $pecounu pečovatelů </h3>";
  $html.= "V tomto počtu nejsou zahrnuti pomocní a osobní pečovatelé, jejichž náklady hradí rodiče
           <br>(to je třeba v evidenční kartě pečovatele zapsat zaškrtnutím políčka pod poznámkou)
           <br>(od roku 2024 se zohledňují částečné pobyty a poloviční porce)";
  // stravenky nejsou vytištěny pro $note, kteří nemají jasnou funkci -- pfunkce=0
//  $html.= $ret->note ? "{$ret->note}<br>" : '';
//  $html.= "<br><table class='stat'><td>položky</td><th>cena</td></tr>";
//  $html.= "$rows_vydaje</table>";
  
  $html.= "<br><br><table class='stat'>$rows_vydaje</table>";

//  foreach ($pecouni as $hnizdo) {
//    if (!$hnizdo->pocet) continue;
//    $html.= $hnizdo->nazev 
//        ? "<h3>... hnízdo {$hnizdo->nazev} - {$hnizdo->pocet} pečovatelů</h3>" : '<br><br>';
//    $html.= $hnizdo->html;
//  }
  
  $html.= "<h3>Shrnutí pro pečovatele</h3>";
  $obrat= $prijmy - $vydaje;
  $prijmy= number_format($prijmy, 0, '.', ' ')."&nbsp;Kč";
  $vydaje= number_format($vydaje, 0, '.', ' ')."&nbsp;Kč";
  $obrat= number_format($obrat, 0, '.', ' ')."&nbsp;Kč";
  $html.= "Účastníci přispějí na děti a pečovatele částkou $prijmy, 
    <br>přímé náklady na stravu pečovatelů činí $vydaje, 
    <br>celkem <b>$obrat</b> zůstává na program dětí a pečovatelů.";
  $html.= "<br><br><br><span style='color:red'><b>DISCLAIMER</b>: "
      . "výpočet vychází pouze z údajů evidovaných v Answeru"
      . "<br><br>Neumí proto zahrnout"
      . "<br>příjmy: částka ušetřená za odřeknuté stravy, ..."
      . "<br>výdaje: ubytování pečovatelů, vicenáklady pečounů (pokoje se sprchami, ...), ..."
      . "<br><br>"
      . "mohl by umět ale neumí: přímé vyplacení stravy některým pečounům t.b.d."
      . "</span>";
end:
  // předání výsledku
  $result->html= $html;
  return $result;
}
# -------------------------------------------------------------------------------- tisk2 text_vyroci
function tisk2_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $cond= "id_akce=$akce $jen_hnizdo AND p.funkce NOT IN (9,10,13,14,15)";
  $result= (object)array('_error'=>0);
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role',
    "$cond AND CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(narozeni,5,6)");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $res= tisk2_qry('pobyt_dospeli_ucastnici','datsvatba',
    "$cond AND CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(datsvatba,5,6)");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci['s'][]= "$x->_jm|".sql_date1($x->datsvatba);
  }
  // nepřivítané děti mladší 2 let
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role,ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek',
    "$cond AND role='d' AND o.uvitano=0","_vek<2","prijmeni");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci['v'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // redakce
  if ( isset($vyroci['s']) && count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  if ( isset($vyroci['a']) && count($vyroci['a']) ) {
    $html.= "<h3>Narozeniny dopělých na akci</h3><table>";
    foreach($vyroci['a'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný dospělý účastník narozeniny</h3>";
  if ( isset($vyroci['d']) && count($vyroci['d']) ) {
    $html.= "<h3>Narozeniny dětí na akci</h3><table>";
    foreach($vyroci['d'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádné dítě narozeniny</h3>";
  if ( isset($vyroci['v']) && count($vyroci['v']) ) {
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
# ------------------------------------------------------------------------------- akce2 text_prehled
# pokud $title='' negeneruje se html
function akce2_text_prehled($akce,$title) { trace();
  global $USER;
  $org= $USER->org;
  $pocet= 0;
  # naplní histogram podle $cond
  $akce_text_prehled_x= function ($akce,$cond,
      $uvest_jmena=false,$bez_tabulky=false,$jen_pod_18=false) use (&$pocet) {
    $html= '';
    // data akce
    $veky= $kluci= $holky= array_fill(0,99,0);
    $nveky= $nkluci= $nholky= 0;
    $jmena= $deljmena= '';
    $bez= $del= '';
    // histogram věku dětí parametrizovaný přes $cond
    $qo=  "SELECT prijmeni,jmeno,narozeni,IFNULL(role,0),a.datum_od,o.sex,id_pobyt
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,0)
           WHERE a.id_duakce='$akce' AND $cond AND funkce NOT IN (9,10,13,14,15) 
           GROUP BY o.id_osoba ORDER BY prijmeni ";
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
      if ($jen_pod_18 && $vek>=18) continue;
      $pocet++;
      $sex= $o->sex;
      $veky[$vek]++;
      $nveky++;
      if ( $sex==1 ) { $kluci[$vek]++; $nkluci++; }
      elseif ( $sex==2 ) { $holky[$vek]++; $nholky++; }
      else { $bez.= "$del{$o->prijmeni} {$o->jmeno}"; $del= ", "; }
      if ( $uvest_jmena ) {
        $jmena.= $deljmena;
        $jmena.= tisk2_ukaz_pobyt($o->id_pobyt,"{$o->prijmeni} {$o->jmeno}/$vek");
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
  $result= (object)array('html'=>'','pozor'=>'');
  $html= '';
  $nedeti= $org!=4
    ? $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role NOT IN (2,3,4,5)",1,1,1)
    : '';
  if ( $pocet>0 ) {
    $html.= "<h3 style='color:red'>POZOR! Děti vedené chybně jako účastníci nebo hosté</h3>$nedeti";
    $result->pozor= "Děti vedené chybně jako účastníci nebo hosté: $nedeti";
  }
  // pfunkce: 0 4 5 8 92 95
  // pfunkce: 1=hlavoun, 2=instruktor, 3=pečovatel, 4=pomocný, 5=osobní, 6=mimořádný, 7=team, 8=člen G
  // funkce=99  -- pečoun, funkce=9,10,13,14,15 -- není na akci
  // s_role=2   -- dítě, s_role=3  -- dítě s os.peč, s_role=4  -- pom.peč, s_role=5  -- os.peč
  // dite_kat=7 -- skupina G
  // děti ...
  if ( $title ) {
    $html.= "<h2>Informace z karty Účastníci (bez náhradníků)</h2>
      <h3>Celkový počet dětí rodin na akci podle stáří (v době začátku akce) - bez os.pečounů včetně pom.pečounů</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (0,1,2,3,4)");
    $html.= "<h3>Děti ve skupinkách (mimo G a osobně opečovávaných)</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (2,4) AND s.dite_kat!=7");
    $html.= "<h3>Děti v péči osobního pečovatele</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (3)",1);
    $html.= "<h3>Děti ve skupině G</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.dite_kat=7",true);
    $html.= "<h3>Pomocní pečovatelé</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (4)",1);
    // pečouni ...
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
  }
  return $result;
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
# ---------------------------------------------------------------------------------------- tisk2 qry
# frekventované SQL dotazy s parametry
# pobyt_dospeli_ucastnici => _jm=jména dospělých účastníků (GROUP BY id_pobyt)
# ucastnik                => každý účastník zvlášť
# pobyt_rodiny            => _jmena, _adresa, _telefony, _emaily
function tisk2_qry($typ,$flds='',$where='',$having='',$order='') { //trace();
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
  case 'pobyt_dospeli_ucastnici': // a i nedospělí pečouni
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
        JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba
      $where AND IF(funkce=99,1,IF(MONTH(pso.narozeni),DATEDIFF(a.datum_od,pso.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(pso.narozeni))>18) 
      GROUP BY p.id_pobyt $having $order
    ";
    break;
  }
  $res= pdo_qry($qry);
  return $res;
}
# -------------------------------------------------------------------------------------- tisk2 table
function tisk2_table($tits,$flds,$clmn,$export=false,$prolog='') {  trace();
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
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
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else {
//                                 debug($c,$f); return $ret;
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $prolog= $prolog ?: "Seznam má $n řádků<br><br>";
    $ret->html= "$prolog<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
# ======================================================================================> . skupinky
# ----------------------------------------------------------------------------- akce2 jednou_dvakrat
# generování seznamu jedno- a dvou-ročáků spolu s mailem na VPS
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export=false) { trace();
  global $VPS;
  $result= (object)array('html'=>'');
  $vps= array();
  $n= 0;
  $qry=  "SELECT
            r.nazev as nazev,
            ( SELECT CONCAT(nazev,' ',emaily,' ',email,'/',funkce )
              FROM rodina
              JOIN tvori ON rodina.id_rodina=tvori.id_rodina
              JOIN osoba ON osoba.id_osoba=tvori.id_osoba
              JOIN spolu ON spolu.id_osoba=osoba.id_osoba
              JOIN pobyt ON pobyt.id_pobyt=spolu.id_pobyt
              WHERE pobyt.id_akce='$akce' AND skupina=p.skupina AND role='a'
              ORDER BY funkce DESC LIMIT 1) as skup,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND p.skupina!=0
          GROUP BY id_pobyt HAVING _ucasti IN (1,2)
          ORDER BY nazev";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $par= "{$x->_ucasti}x {$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}";
    $vps[$x->skup][]= $par;
    $n++;
  }
  ksort($vps);
//                                         debug($vps,"jednou - dvakrát");
  $html= "<h3>Pracovní seznam párů, kteří jsou na MS poprvé (1x) nebo podruhé (2x), spolu s jejich $VPS</h3>";
  foreach ($vps as $v => $ps) {
    $html.= "<p><b>$v</b>";
    foreach ($ps as $p) {
      $html.= "<br>$p";
    }
    $html.= "</p>";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_copy
# ASK
# přenese skupinky z LK do Obnovy nebo z PO do JO
function akce2_skup_copy($obnova,$podle='LK') { trace();
  $msg= "Kopii nelze provést";
  // najdeme LK
  list($access,$druh,$kdy)= select('access,druh,datum_od','akce',"id_duakce=$obnova");
  if ( $druh==2 /*MS obnova*/ ) {
    if ($podle=='LK') {
      $lk= select('id_duakce','akce',
        "access=$access AND druh=1 /*MS LK*/ AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
    }
    else {
      // má smysl jen pro jarní
      if (substr($kdy,5,2)>7) { $msg= "tato operace nemá smysl pro PO"; goto end; }
      $lk= select('id_duakce','akce',
        "access=$access AND druh=2 /*MS PO */ AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
    }
  }
  else { $msg= "Skupinky z LK lze zkopírovat jen pro obnovy MS"; goto end; }
  // nesmí být rozpracované skupinky
  $skupinky= select('COUNT(*)','pobyt',"skupina!=0 AND id_akce=$obnova");
  if ( $skupinky ) {
    $msg= "Skupinky na této obnově jsou již částečně navrženy, kopii z $podle nelze provést";
    goto end;
  }
  // vše ok, provedeme přenos ... šlo by to i čistě v SQL
  //UPDATE pobyt AS jo
  //JOIN pobyt AS lk ON jo.i0_rodina=lk.i0_rodina AND lk.id_akce=1131 AND lk.skupina!=0
  //SET jo.skupina=lk.skupina
  //WHERE jo.id_akce=1255 AND jo.skupina=0  
  $n= 0;
  $rr= pdo_qry("SELECT lk.i0_rodina,lk.skupina,r.nazev
      FROM pobyt AS lk 
      JOIN pobyt AS jo ON jo.id_akce=$obnova AND jo.i0_rodina=lk.i0_rodina
      JOIN rodina AS r ON r.id_rodina=lk.i0_rodina
      WHERE lk.id_akce=$lk AND lk.skupina!=0
      ORDER BY skupina");
  while ( $rr && (list($idr,$skupina,$nazev)= pdo_fetch_array($rr)) ) {
    display("$skupina = $nazev ($idr)");
//    $n++;
    $rs= query("UPDATE pobyt SET skupina=$skupina 
      WHERE id_akce=$obnova AND i0_rodina=$idr AND skupina=0 AND funkce IN (0,1,2,5) ");
    $n+= pdo_affected_rows($rs);
  }
  $msg= "Na obnovu bylo pro $n párů zkopírováno číslo skupinky z $podle (pokud ještě číslo neměli)";
end:
  return $msg;
}
# ---------------------------------------------------------------------------------- akce2 skup_tisk
# tisk skupinek akce
# pro akce v hnízdech jsou skupinky číslovány lokálně vzestupnou řadou - není-li par->precislovat=0
function akce2_skup_tisk($akce,$par,$title,$vypis,$export) {  trace();
  global $VPS;
  $result= (object)array();
  $html= "<table>";
  $ret= akce2_skup_get($akce,0,$err,$par);
  $hnizda= select('hnizda','akce',"id_duakce=$akce");
                                                       debug($ret);
  $skupiny= $ret->skupiny;
  // pro par.mark=LK zjistíme účasti rodin na obnově
  $lk= 0;
  $na_kurzu= $na_obnove= $nahrada= $vps= $umi_vps= array();     // $nahrada = na obnově náhradnici => id_rodina->1
  if ( isset($par->mark) && ($par->mark=='LK' || $par->mark=='PO') ) {
    // chyba=-1 pro kombinaci par.mark=LK a akce není obnova MS
    if ( $err==-1 ) { $result->html= $ret->msg; display("err=$err");  goto end; }
    $lk= 1;
    // seznam rodin LK či PO - účastníci
    $rr= pdo_qry("SELECT i0_rodina FROM pobyt AS p
    WHERE p.id_akce={$ret->lk} AND funkce IN (0,1,2,5,6) ");
    while ( $rr && (list($idr,$nazev)= pdo_fetch_array($rr)) ) {
      $na_kurzu[$idr]= 1;
    }
    // seznam rodin obnovy
    $lk_nebyli= 0;
    $rr= pdo_qry("
      SELECT i0_rodina,CONCAT(nazev,' ',GROUP_CONCAT(jmeno ORDER BY role SEPARATOR ' a ')),funkce,
        FIND_IN_SET('1',r_umi)
      FROM pobyt AS p
      JOIN rodina AS r ON r.id_rodina=i0_rodina
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      WHERE id_akce=$akce AND role IN ('a','b') AND funkce IN (0,1,2,5)
      GROUP BY i0_rodina
      ORDER BY nazev");
    while ( $rr && (list($idr,$nazev,$funkce,$umi)= pdo_fetch_array($rr)) ) {
      $x= '';
      if ( !isset($na_kurzu[$idr]) ) {
        $lk_nebyli++;
        $x= $nazev . ($funkce==9 ? " (náhradníci)" : '');
      }
      $na_obnove[$idr]= $x;
      if ( $funkce==1 ) $vps[$idr]= 1;
      elseif ( $umi )   $umi_vps[$idr]= 1;
      if ( $funkce==9 ) $nahrada[$idr]= 1;
    }
  }
  $n= 0;
  if ( $export ) {
    $clmn= $atrs= array();
    $poradi= 1; $c_skupina= 0;
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
        $cislo_skupiny= $c->skupina;
        if ($par->precislovat && $c_skupina!=$c->skupina) {
          $cislo_skupiny= $hnizda ? $poradi++ : $c->skupina;
          $c_skupina= $c->skupina;
        }
        $clmn[$n]['skupina']= $i==$c->id_pobyt ? $cislo_skupiny : '';
        $clmn[$n]['jmeno']= $c->_nazev;
        if ( !$lk )
          $clmn[$n]['pokoj']= $i==$c->id_pobyt ? $c->pokoj : '';
        else {
          // pro LK přidáme atribut nezúčastněným
          if ( !isset($na_obnove[$c->i0_rodina]) ) {
            $atrs[$n]['jmeno']= "bcolor=ffdddddd";
            $clmn[$n]['jmeno']= '- '.$clmn[$n]['jmeno'];
          }
          // resp. náhradníkům
          else if ( isset($nahrada[$c->i0_rodina]) ) {
            $atrs[$n]['jmeno']= "bcolor=ffdddddd";
            $clmn[$n]['jmeno']= '+ '.$clmn[$n]['jmeno'];
          }
        }
        $n++;
      }
      $clmn[$n]['skupina']= $clmn[$n]['jmeno']= '';
      if ( !$lk )
        $clmn[$n]['pokoj']= '';
      $n++;
    }
    // pro LK přidáme seznam, co nebyli v létě
    $skup= 'bez LK';
    if ( $lk ) {
      if ( $lk_nebyli ) {
        foreach ($na_obnove as $nazev) {
          if ( $nazev ) {
            $clmn[$n]['skupina']= $skup; $skup= '';
            $clmn[$n]['jmeno']= $nazev;
            $n++;
          }
        }
      }
      else {
        $clmn[$n]['skupina']= $skup;
        $clmn[$n]['jmeno']= '-';
      }
    }
    // předání pro tisk2_vyp_excel
    $result->tits= explode(',',"skupinka:10,jméno:30".($lk ? '' : ",pokoj $VPS:10:r"));
    $result->flds= explode(',',"skupina,jmeno".($lk ? '' : ",pokoj"));
    $result->clmn= $clmn;
    $result->atrs= $atrs;
    $result->expr= null;
  }
  else {
    $xn= 0; $tabulka= '';
    $poradi= 1; $c_skupina= 0;
    foreach ($skupiny as $i=>$s) {
      $tab= "<table>";
      foreach ($s as $c) {
        $cislo_skupiny= $c->skupina;
        if ($par->precislovat && $c_skupina!=$c->skupina) {
          $cislo_skupiny= $hnizda ? "$poradi ($c->skupina)" : $c->skupina;
          $c_skupina= $c->skupina;
          $poradi++;
        }
        $nazev= $c->_nazev.($lk ? (isset($vps[$c->i0_rodina]) 
            ? " - VPS" : (isset($umi_vps[$c->i0_rodina]) ? " (vps)" : '')) : '');
        $pokoj= $lk ? '' : $c->pokoj;
        if ( $lk && !isset($na_obnove[$c->i0_rodina]) ) {
          $nazev= "<s>$nazev</s>";
          $xn++;
        }
        elseif ( $lk && isset($nahrada[$c->i0_rodina]) ) {
          $nazev= "<s>$nazev (náhradníci)</s>";
          $xn++;
        }
        if ( $i==$c->id_pobyt )
          $tab.= "<tr><th>$cislo_skupiny</th><th>$nazev</th><th>$pokoj</th></tr>";
        else
          $tab.= "<tr><td></td><td>$nazev</td><td></td></tr>";
      }
      $tab.= "</table>";
      if ( $n%2==0 )
        $tabulka.= "<tr><td>&nbsp;</td></tr><tr><td valign='top'>$tab</td>";
      else
        $tabulka.= "<td valign='top'>$tab</td></tr>";
      $n++;
    }
    if ( $n%2==1 )
      $tabulka.= "<td></td></tr>";
    $tabulka.= "</table>";
    if ( $lk ) {
      $setkani= $par->mark=='LK' ? 'posledního letního kurzu' : 'poslední obnovy';
      $html.= "<h3>Skupinky z $setkani se škrtnutými (je jich $xn)
        nepřihlášenými na obnovu</h3>$tabulka";
    }
    else 
      $html.= $tabulka;
    // pro mark=LK zobraz ty, co nebyly na kurzu
    if ( $lk ) {
      if ( $lk_nebyli ) {
        $n= 0; $pary= '';
        foreach ($na_obnove as $idr=>$nazev) {
          if ( $nazev ) {
            $pary.= "$nazev".(isset($vps[$idr])?' - VPS':(isset($umi_vps[$idr]) ? " (vps)" : ''))."<br>";
            $n++;
          }
        }
        $posledni= $par->mark=='LK' ? 'posledním letním kurzu' : 'poslední obnově';
        $html.= "<h3>Na $posledni  nebylo $n párů:</h3>$pary";
      }
      else {
        $html.= "<h3>Všichni přihlášení byli na posledním setkání</h3>";
      }
    }
    if ($hnizda && $par->precislovat) {
      $html= "<b>Poznámka</b>: V závorce je vždy uvedeno číslo skupinky v rámci celé akce 
        - tedy údaj u pobytu na panelu Účastníci.".$html;
    }
    $result->html= $html;
  }
end:
//                                                 debug($result,"akce2_skup_tisk($akce,,$title,$vypis,$export)");
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_paru
# přehled všech skupinek letního kurzu MS daného páru (rodiny) -> html
# počet let aktivního VPSkování -> sluzba, počet odpočinkových skupinek -> odpocinek
# počet účastí na MS -> ucasti
function akce2_skup_paru($idr) { //trace();
  $html= '';
  $sluzba= $odpocinek= $vps= 0;
  $n_ms= 0;
  // projdi LK ve kterých byli ve skupince
  $ru= pdo_qry("
    SELECT id_akce,skupina,YEAR(datum_od) as rok
    FROM akce AS a
    JOIN pobyt AS p ON a.id_duakce=p.id_akce AND i0_rodina=$idr
    WHERE a.druh=1 AND skupina!=0
    GROUP BY rok
    ORDER BY datum_od DESC
  ");
  while ( $ru && (list($ida,$skup,$rok)= pdo_fetch_array($ru)) ) {
    $html.= "<br>&nbsp;&nbsp;&nbsp;<b>$rok</b> ";
    $n_ms++;
    // prober skupinku
    $rs= pdo_qry("
      SELECT funkce,nazev,id_rodina
      FROM pobyt AS p
      JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      WHERE p.id_akce=$ida AND skupina=$skup
      ORDER BY IF(p.funkce IN (1,2),'',nazev)
    ");
    while ( $rs && (list($fce,$nazev,$ir)= pdo_fetch_array($rs)) ) {
      if ( $ir==$idr && $fce==1  ) 
        $vps++;
      if ( $ir==$idr && !$odpocinek ) {
        if ( $fce==1  ) 
          $sluzba++;
        else
          $odpocinek= $rok;
      }
      $par= " $nazev".($fce==1||$fce==2 ? ' (VPS) ' : '');
      $html.= $ir==$idr ? "<i>$par</i>" : $par;
    }
  }
  if ( !$n_ms ) $html= "nebyli na MS v žádné skupince";
  return (object)array('sluzba'=>$sluzba,'odpocinek'=>$odpocinek,'vps'=>$vps,
      'ucasti'=>$n_ms,'html'=>$html);
}
# ---------------------------------------------------------------------------------- akce2 skup_hist
# přehled starých skupinek letního kurzu MS účastníků této akce
function akce2_skup_hist($akce,$par,$title,$vypis,$export) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $result= (object)array();
  // letošní účastníci
  $letos= array();
  $rok= 0;
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,p.funkce as funkce,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),1) as jmeno_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''),1) as jmeno_z,
            id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $u->nazev= str_replace(' ','-',$u->nazev);
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o iniciály křestních jmen
  $old= 0; $old_nazev= '';
  foreach ($letos as $muz=>$info) {
    if ( $old_nazev==$info->_nazev ) {
      $inic_old= $letos[$old]->jmeno_m.'+'.$letos[$old]->jmeno_z;
      $inic_muz= $letos[$muz]->jmeno_m.'+'.$letos[$muz]->jmeno_z;
      $letos[$old]->_nazev= $letos[$old]->nazev.'&nbsp;'.$inic_old;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.'&nbsp;'.$inic_muz;
    }
    $old= $muz;
    $old_nazev= $info->_nazev;
  }
//                                                         debug($odkud);
  // tisk
  $td= "td style='border-top:1px dotted grey'";
  $th= "th style='border-top:1px dotted grey;text-align:right'";
  $html= "<table>";
  foreach ($letos as $muz=>$info) {
    // minulé účasti
    $n= 0;
    $qry= " SELECT p.id_akce,skupina,year(datum_od) as rok
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= pdo_qry($qry);
    $ucasti= '';
    while ( $res && ($r= pdo_fetch_object($res)) ) {
      $n++;
      // minulé skupinky
      $qry_s= "
            SELECT GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            WHERE p.id_akce={$r->id_akce} AND skupina={$r->skupina}
            GROUP BY id_pobyt HAVING FIND_IN_SET(id_osoba_m,'$letosni')
            ORDER BY datum_od DESC ";
      $res_s= pdo_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= pdo_fetch_object($res_s)) ) if ( $s->id_osoba_m!=$muz ) {
        $spolu.= "$del{$letos[$s->id_osoba_m]->_nazev}";
        $del= ',&nbsp;';
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}</u>:&nbsp;$spolu";
      }
    }
    if ( $ucasti )
      $html.= "<tr><$th>{$info->_nazev}</th><$th>$n&times;</th><$td>$ucasti</td></tr>";
//    elseif ( $n )
    else
      $html.= "<tr><$th>{$info->_nazev}</th><$th>$n&times;</th><$td>-</td></tr>" ;
  }
  $html.= "</table>";
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. <br>Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok. (Pro tisk je nejjednodušší označit jako blok a vložit do Excelu.)";
  $html= "<i>$note</i><br>$html";
  //$result->html= nl2br(htmlentities($html));
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_popo
# přehled pro tvorbu virtuální obnovy
function akce2_skup_popo($akce,$par,$title,$vypis,$export) { trace();
  $male_dite= 9; // hranice pro upozornění na malé dítě v rodine
  $obnova= 1;     //1=jarní 2=podzimní
  $result= (object)array();
  // letošní účastníci
  $letos= $skup_vps= $znami= $stejny_nazev= array();
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,month(datum_od) as mes,
            p.funkce as funkce,
            IF(FIND_IN_SET(1,r_umi),1,0) AS _vps,r_ms,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),1) as jmeno_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''),1) as jmeno_z,
            ( SELECT CONCAT(COUNT(*),';',
                -- IFNULL(GROUP_CONCAT(ROUND(DATEDIFF(a.datum_od,narozeni)/365.2425) ORDER BY narozeni DESC),''))
                IFNULL(GROUP_CONCAT(ROUND(IF(MONTH(narozeni),DATEDIFF(a.datum_od,narozeni)/365.2425,YEAR(a.datum_od)-YEAR(narozeni))) ORDER BY narozeni DESC),''))
              FROM tvori JOIN osoba USING (id_osoba)
              WHERE id_rodina=i0_rodina AND role='d'
            ) AS _deti,id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          JOIN rodina AS r ON r.id_rodina=p.i0_rodina
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5) 
          GROUP BY id_pobyt
          ORDER BY funkce,_vps,r.nazev ";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $u->nazev= str_replace(' ','-',$u->nazev);
    $obnova= $u->mes<7 ? 1 : 2;
    if (isset($stejny_nazev[$u->nazev]))
      $stejny_nazev[$u->nazev]++;
    else
      $stejny_nazev[$u->nazev]= 1;
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $letos[$muz]->ms= $u->r_ms;
    if ($u->funkce==1) {
      $letos[$muz]->vps= 'VPS';
      if ($u->skupina)
        $skup_vps[$u->skupina]= $muz;
    }
    $letos[$muz]->skup= $u->skupina;
    // rozbor dětí
    $deti= '';
    $d_nr= explode(';',$u->_deti);
    if ($d_nr[0]) {
      $d_r= explode(',',$d_nr[1]);
      if ($d_r[0]<=$male_dite) {
        $deti= 'děti';
      }
    }
    $letos[$muz]->deti= $deti;
    // rozbor umí
    if ($u->funkce!=1 && $u->_vps)
      $letos[$muz]->vps= '(vps)';
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o iniciály křestních jmen
  foreach ($letos as $muz=>$info) {
    if ( $stejny_nazev[$info->_nazev]>1 ) {
      $inic= $letos[$muz]->jmeno_m.'+'.$letos[$muz]->jmeno_z;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.'&nbsp;'.$inic;
    }
  }
//                                                         debug($letos);
  foreach ($letos as $muz=>$info) {
    // minulé účasti na LK
    $n= $n_lk= 0;
    $qry= " SELECT p.id_akce,druh,skupina,year(datum_od) as rok,
              IF(a.nazev LIKE 'MLS%','m',IF(druh=2,'o','')) AS _druh
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING (id_pobyt)
            JOIN rodina AS r ON r.id_rodina=p.i0_rodina
            WHERE a.druh IN (1,2) AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= pdo_qry($qry);
    $ucasti= '';
    while ( $res && ($r= pdo_fetch_object($res)) ) {
      $n++;
      if ($r->druh==1) $n_lk++;
      // minulé skupinky - včetně obnov
      $qry_s= "
            SELECT GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as _muz
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            WHERE p.id_akce={$r->id_akce} AND skupina={$r->skupina} AND skupina!=0
            GROUP BY id_pobyt HAVING FIND_IN_SET(_muz,'$letosni')
            ORDER BY datum_od DESC ";
      $res_s= pdo_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= pdo_fetch_object($res_s)) ) {
        if ( $s->_muz!=$muz ) {
          // vytvoření tipů - vynecháme VPS a ty, co už mají skupinku
          if (!$letos[$s->_muz]->vps && !$letos[$s->_muz]->skup) {
            $s_nazev= $letos[$s->_muz]->_nazev;
            $s_nazev= $r->_druh ? "? $s_nazev" : $s_nazev;
            if (isset($znami[$muz])) {
              if (!in_array($s_nazev,$znami[$muz])) {
                $znami[$muz][]= $s_nazev;
              }
            }
            else {
              $znami[$muz]= array($s_nazev);
            }
          }

          if ($letos[$s->_muz]->vps=='VPS') {
            $spolu.= "$del<b>{$letos[$s->_muz]->_nazev}</b>";
          }
          else {
            $spolu.= "$del{$letos[$s->_muz]->_nazev}";
          }
          $del= ',&nbsp;';
        }
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}{$r->_druh}</u>:&nbsp;$spolu";
      }
    }
    // přidáme účasti na jiném kurzu
    $info->ms= $info->ms ? "$n_lk+{$info->ms}" : $n_lk;
    // redakce výpisu
    if ($info->skup && $info->vps!='VPS') {
      $vps= isset($skup_vps[$info->skup]) ? $skup_vps[$info->skup] : '';
      if ($vps) {
        $letos[$vps]->lidi.= " + {$info->_nazev} ";
      }
    }
    $info->ucasti= $ucasti;
  }
//                                                        debug($znami);
  // tisk
  $td= "td style='border-top:1px dotted grey'";
  $th= "th style='border-top:1px dotted grey;text-align:right'";
  $tl= "th style='border-top:1px dotted grey;text-align:left'";
  $cast= 'ucastnici';
  $html= "<h3>Účastníci ... s kým a kdy byli ve skupince 
          (v roce zakončeném: 'o' na obnově, 'm' na mlsu, jinak na letním kurzu)</h3><table>";
  foreach ($letos as $muz=>$info) {
    $skup= $info->skupina ? "{$info->skupina}.&nbsp;skup. " : '';
    if ($cast=='ucastnici' && $info->vps=='(vps)') {
      $cast= '(vps)';
      $html.= "</table><h3>Odpočívající VPS ... s kým a kdy byli ve skupince</h3><table>";
    }
    if (($cast=='(ucastnici'||$cast=='(vps)') && $info->vps=='VPS') {
      $cast= 'VPS';
      $html.= "</table><h3>VPS ve službě ... '+' označuje složení skupinky ... 
        '?' s kým se znají z LK '??' s kým se znají z obnov a mlsů (vše bez VPS)</h3><table>";
    }
    if ($info->vps!='VPS') {
      $html.= "<tr><td>$skup </td><td>{$info->_nazev}</td><$th>{$info->ms}&times;LK</th>
                   <$tl>$info->deti</th><$td>{$info->ucasti}</td></tr>" ;
    }
    else { // VPS
      $tips= $znami[$muz] ? implode(' ?',$znami[$muz]) : '';
      $tips= $tips ? " ... ( ?$tips )" : '';
      $html.= "<tr><th>$skup</th><$tl>{$info->_nazev}</th><$th>{$info->ms}&times;LK</th>
                   <$tl>$info->deti</th><$td>{$info->lidi} $tips</td></tr>" ;
    }
  }
  $html.= "</table>";
  $obnovy= $obnova==1 ? "Jarní virtuální obnovy" : "Podzimní virtuální obnovy";
  $note= "<h3>Pomůcka pro vytvoření $obnovy</h3>
    Zobrazují se údaje <ul>
    <li> skupinka a funkce
    <li> počet účastí na LK 
    <li> poznámka <b>děti</b> pokud mají malé děti (do $male_dite let) 
    <li> seznam lidí, se kterými již v minulosti byli ve skupince (aktuální VPS jsou tučně)
    <li> ve spodní části s VPS jsou zapsány členi skupinky (mají před jménem +) a tipy na ně v závorce
    </ul>
    ";
  $html= "<i>$note</i><br>$html";
  //$result->html= nl2br(htmlentities($html));
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_deti
# tisk skupinek akce dětí
function akce2_skup_deti($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  // celkový počet dětí na kurzu
  $qry= "SELECT SUM(IF(t.role='d',1,0)) AS _deti,SUM(IF(funkce=99,1,0)) AS _pecounu
         FROM akce AS a
         JOIN pobyt AS p ON a.id_duakce=p.id_akce
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
         WHERE id_duakce='$akce' AND p.funkce NOT IN (9,10,13,14,15)
         GROUP BY id_duakce ";
  $res= pdo_qry($qry);
  $pocet= pdo_fetch_object($res);
  if (!$pocet) return $result;
//                                                         debug($pocet,"počty");
  // zjištění skupinek
  $skupiny= array();   // [ skupinka => [{fce,příjmení,jméno},....], ...]
  $qry="SELECT id_pobyt,skupinka,funkce,prijmeni,jmeno,narozeni,rc_xxxx,datum_od
        FROM osoba AS o
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce  AS a ON id_duakce='$akce'
        WHERE  id_akce='$akce' AND skupinka!=0 AND p.funkce NOT IN (9,10,13,14,15)
        ORDER BY skupinka,IF(funkce=99,0,1),prijmeni,jmeno ";
  $res= pdo_qry($qry);
  while ( $res && ($o= pdo_fetch_object($res)) ) {
    $o->_vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
    $skupiny[$o->skupinka][]= $o;
  }
//                                                         debug($skupiny,"skupiny");
  $n= 0;
  $deti_in= $pecouni_in= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
//                                                         debug($c,"$i");
        $clmn[$n]['skupinka']= $c->funkce==99 ? $c->skupinka : '';
        $clmn[$n]['prijmeni']= $c->prijmeni;
        $clmn[$n]['jmeno']= $c->jmeno;
        $clmn[$n]['vek']= $c->_vek;
        $n++;
      }
      $clmn[$n]['skupinka']= $clmn[$n]['jmeno']= $clmn[$n]['prijmeni']= $clmn[$n]['vek']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,příjmení:20,jméno:20,věk:4:r");
    $result->flds= explode(',',"skupinka,prijmeni,jmeno,vek");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    $tab= '';
    $r= "style='text-align:right'";
    foreach ($skupiny as $i=>$s) {
      $tab.= "<table class='stat'>";
      $tab.= "<tr><th width=200 colspan=3>Skupinka $i</th></tr>";
      foreach ($s as $o) {
        if ( $o->funkce==99 ) {
          $tab.= "<tr><th>{$o->prijmeni}</th><th>{$o->jmeno}</th><th $r>{$o->_vek}</th></tr>";
          $pecouni_in++;
        }
        else {
          $tab.= "<tr><td>{$o->prijmeni}</td><td>{$o->jmeno}</td><td $r>{$o->_vek}</td></tr>";
          $deti_in++;
        }
      }
      $tab.= "</table><br/>";
    }
    $deti_out= $pocet->_deti - $deti_in;
    $pecouni_out= $pocet->_pecounu - $pecouni_in;
    $msg= $deti_out>0 ? "Celkem $deti_out dětí není zařazeno do skupinek": '';
    if ( $deti_out>0 ) fce_warning($msg);
    $msg.= $pecouni_out>0 ? "<br>Celkem $pecouni_out pečounů není zařazeno do skupinek<br><br>": '';
    $result->html= "$msg$tab";
  }
//                                                         debug($result,"result");
  return $result;
}
function akce2_tabulka_stat($akce,$par,$title,$vypis,$export=0) { trace();
  global $tisk_hnizdo, $ezer_version;
  $result= (object)array();
  $html= "";
  $err= "";
  $pobyt= array();
  // akce
  list($nazev_akce,$datum_od)= select('nazev,datum_od','akce',"id_duakce=$akce");
  // účastníci
  $qry=  "SELECT
          id_pobyt,nazev,svatba,datsvatba,
          ( SELECT GROUP_CONCAT(CONCAT(role,narozeni) ORDER BY role,narozeni DESC)
            FROM osoba JOIN tvori USING (id_osoba)
            WHERE id_rodina=i0_rodina AND role IN ('a','b','d') 
          ) AS _cleni
          FROM pobyt AS p
          JOIN rodina AS r ON r.id_rodina=i0_rodina
          WHERE id_akce=$akce AND p.hnizdo=$tisk_hnizdo AND p.funkce IN (0,1,2,5)  -- včetně hospodářů, bývají hosty skupinky
          -- AND id_pobyt=54030
          GROUP BY id_pobyt
          ORDER BY nazev
          -- LIMIT 1
  ";
//   $qry.= " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && (list($idp,$nazev,$sv1,$sv2,$xcleni)= pdo_fetch_row($res)) ) {
    $pobyt[$idp]['name']= $nazev;
    // délka manželství
    $manzelstvi= 
        $sv2 ? sql2stari($sv2,$datum_od) : (
        $sv1 ? sql2stari("$sv1-07-01",$datum_od) : 999);
    $pobyt[$idp]['m'][]= $manzelstvi;
    foreach ( explode(',',$xcleni) as $xclen) {
      $role= substr($xclen,0,1);
      $narozeni= substr($xclen,1);
      $roku= sql2stari($narozeni,$datum_od);
      $pobyt[$idp][$role][]= $roku;
    }
  }
//                                                              debug($pobyt,"1");
  // zpracování podle intervalů
  $kat= array(
    'm' => array('manželství',array(9,20,999)),                 // délka manželství
    'r' => array('věk rodičů',array(30,45,60,75,999)),          // průměrný věk rodičů a/b
    'x' => array('od sebe',   array(5,10,999)),                 // rozdíl věku rodičů a/b
    'd' => array('věk dětí',  array(7,18,30,999)),              // věk dětí d
  );
  $stari= array();
  foreach ($pobyt as $idp=>$cleni) {
    $cleni['r'][]= round(($cleni['a'][0]+$cleni['b'][0])/2); 
    $cleni['x'][]= abs($cleni['a'][0]-$cleni['b'][0]); 
    foreach ($kat as $k=>$xdelims) {
      if ( isset($cleni[$k]) )
      foreach ($cleni[$k] as $stari) {
        foreach ($xdelims[1] as $delim) {
          if ( $stari < $delim) {
            $pobyt[$idp]["-$k"][$delim]++;
            break;
          }
        }
      }
    }
  }
  $title= "Statistika akce $nazev_akce roku ".substr($datum_od,0,4);
  $html.= "<h1>$title</h1><table class='vypis'>";
  $fname= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= "|open $fname|sheet vypis;;L;page\n";
  $_xls= "|A1 $title ::bold size=14 \n|A2 $vypis ::bold size=12\n";
  // hlavička
  $html.= "<tr>";
  $lc= 0;
  $n= 4;
  foreach ($kat as $k=>$xdelims) {
    $cols= count($xdelims[1]);
    $html.= "<th colspan=$cols>{$xdelims[0]}</th>";
    $A= Excel5_n2col($lc);
    $_xls.= "\n|$A$n {$xdelims[0]}";
    $lc+= $cols;
  }
  $_xls.= "\n";
  $n++;
  $html.= "</tr>";
  $html.= "<tr>";
  $lc= 0;
  foreach ($kat as $k=>$xdelims) {
    foreach ($xdelims[1] as $delim) {
      $border= '::border=,,t,';
      if ( $delim==999 ) {
        $delim= '...';
        $border= '::border=,t,t,';
      }
      $html.= "<th>$delim</th>";
      $A= Excel5_n2col($lc++);
      $_xls.= "|$A$n $delim $border";
    }
    $_xls.= "\n";
  }
  $lw= $lc;
  $xls.= "|columns A:$A=5";
  $Aname= Excel5_n2col($lc++);
  $xls.= ",$Aname=25\n";
  $_xls.= "\n|A4:$A$n bcolor=ffc0e2c2 border=t";
//  "|A5:{$A}5 border=t,,t,t\n";
  $xls.= "\n$_xls";
  $n++;
  $html.= "</tr>";
  // data
  foreach ($pobyt as $idp=>$cleni) {
    $html.= "<tr>";
    $lc= 0;
    foreach ($kat as $k=>$xdelims) {
      foreach ($xdelims[1] as $delim) {
        $kn= $pobyt[$idp]["-$k"][$delim];
        $html.= "<td>$kn</td>";
        if ( $kn || $delim==999 ) {
          $A= Excel5_n2col($lc);
          if ( $delim==999 ) $kn.= ' ::border=,t,,';
          $xls.= "\n|$A$n $kn";
        }
        $lc++;
      }
    }
    $name= $pobyt[$idp]['name'];
    $html.= "<th>$name</th>";
    $html.= "</tr>";
    $A= Excel5_n2col($lc++);
    $xls.= "\n|$A$n $name";
    $xls.= "\n";
    $n++;
  }
  $html.= "</table>";
  // časová značka
  $kdy= date("j. n. Y v H:i");
  $n+= 4;
  $xls.= "\n\n|A$n Výpis byl vygenerován $kdy :: italic";
  $xls.= "\n|close";
//                                                                display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls);
  $ref= " Statistika byla vygenerován ve formátu <a href='docs/$fname.xlsx' target='xls'>Excel</a>.";
//                                                                debug($pobyt,"2");
end:
  $result->html= "$err$ref$html";
  return $result;
}
# =======================================================================================> . plachta
# ------------------------------------------------------------------------------- tisk2 pdf_plachta0
# generování pomocných štítků
function tisk2_pdf_plachta0($report_json=0) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $n= 0;
  if ( $report_json) {
    $parss= array();
    // čísla skupinek
    for ($i= 1; $i<=30; $i++ ) {
      $parss[$n]= (object)array();  // {cislo]
      $fs= 20;
      $s1= "font-size:{$fs}mm;font-weight:bold";
      $bg1= ";color:#00aa00;line-height:40mm";
      $ii= $i<10 ? "&nbsp;&nbsp;&nbsp;$i" : "&nbsp;$i";
      $parss[$n]->prijmeni= "<div style=\"$s1$bg1\">$ii</div>";
      $parss[$n]->jmena= '';
      $n++;
    }
    // souřadnicový systém 2x
    for ($k= 1; $k<=1; $k++) {
      for ($i= 1; $i<=14; $i+=2 ) {
        // definice pole substitucí
        $parss[$n]= (object)array();  // {cislo]
        $fs= 22;
        $s1= "font-size:{$fs}mm;font-weight:bold";
        $bg1= ";color:#aa0000;line-height:40mm";
        $ii= $i<10 ? "&nbsp;$i&nbsp;" : "$i";
        $ia= chr(ord('A')+$i-1);
        $ib= chr(ord('A')+$i);
        $fill= $i==9 ? "&nbsp;" : '';
        $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">&nbsp;$fill$ia &nbsp;  $fill$ib</span>";
        $parss[$n]->jmena= '';
        $n++;
      }
      for ($i= 1; $i<=8; $i+=2 ) {
        // definice pole substitucí
        $parss[$n]= (object)array();  // {cislo]
        $fs= 22;
        $s1= "font-size:{$fs}mm;font-weight:bold";
        $bg1= ";color:#aa0000;line-height:40mm";
        $ia= $i<10 ? "&nbsp;$i" : "$i";
        $ib= $i+1;
        $ib= $i+1<10 ? "&nbsp;$ib" : "$ib";
        $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">$ia &nbsp;$ib</span>";
        $parss[$n]->jmena= '';
        $n++;
      }
    }
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    $err= dop_rep_ids($report_json,$parss,$fpath);
    $result->html= $err ? $err
      : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  }
  else {
    $result->html= "pomocné šítky";
  }
  return $result;
}
# ------------------------------------------------------------------------------------ akce2 plachta
# podklad pro tvorbu skupinek
function akce2_plachta($akce,$par,$title,$vypis,$export=0,$hnizdo=0) { trace();
  global $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  $result= (object)array();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $err= "";
  $excel= array();
  // informace
  $par2= (object)array('typ'=>'tab','cnd'=>"p.funkce NOT IN (99)",
      'fld'=>'key_rodina,prijmeni,jmena2,rodice,vek_deti,x_ms,_vps,funkce,^id_pobyt');  
  $c= akce2_tabulka($akce,$par2,'','');
  if (!$c->clmn) return $result;
//                                                debug($c,'akce2_tabulka');
  // získání všech id_pobyt - definice ORDER
  $ids= array_merge($c->vps,$c->nevps,$c->novi,$c->druh,$c->vice);
  $ids= implode(',',$ids);
  $ids_1= implode(',',$c->novi);
  $ids_2= implode(',',$c->druh);
  $ids_3= implode(',',$c->vice);
  $ids_4= implode(',',$c->vps);
  $ids_5= implode(',',$c->nevps);
  $kategorie= "CASE 
      WHEN FIND_IN_SET(id_pobyt,'$ids_1') THEN 1
      WHEN FIND_IN_SET(id_pobyt,'$ids_2') THEN 2
      WHEN FIND_IN_SET(id_pobyt,'$ids_3') THEN 3
      WHEN FIND_IN_SET(id_pobyt,'$ids_4') THEN 4
      WHEN FIND_IN_SET(id_pobyt,'$ids_5') THEN 5
    END  ";
  $vek= "narozeni_m DESC";
  $vzdelani= "_vzdelani";
//  $ids= "2287,3323";
//                                                debug($ids,count($ids));
  $qry=  "SELECT
          id_pobyt,id_rodina,r.nazev as jmeno,
          $kategorie AS _kat,
          r.obec as mesto,svatba,datsvatba,
          SUM(IF(s.s_role IN (2,3),1,0)) AS _detisebou,
          c.hodnota AS _skola,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.vzdelani,'') SEPARATOR '') as vzdelani_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.cirkev,'')   SEPARATOR '') as cirkev_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.aktivita,'') SEPARATOR '') as aktivita_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zajmy,'')    SEPARATOR '') as zajmy_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zamest,'')   SEPARATOR '') as zamest_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_osoba_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.vzdelani,'') SEPARATOR '') as vzdelani_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.cirkev,'')   SEPARATOR '') as cirkev_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.aktivita,'') SEPARATOR '') as aktivita_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zajmy,'')    SEPARATOR '') as zajmy_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zamest,'')   SEPARATOR '') as zamest_z,
          MAX(IF(t.role IN ('a','b'),c.hodnota,0)) as _vzdelani,
          ( SELECT COUNT(*)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS deti,
          ( SELECT MIN(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS maxdeti,
          ( SELECT MAX(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS mindeti
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=i0_rodina
          LEFT JOIN rodina AS r USING (id_rodina)
          LEFT JOIN _cis AS c ON c.druh='ms_akce_vzdelani' AND c.data=o.vzdelani
          WHERE id_pobyt IN ($ids) AND funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY _kat, /*$vzdelani,*/ $vek";
//  $qry.= " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
//    debug($u);
    $idp= $u->id_pobyt;

    
    // minulé účasti - ale ne jako děti účastnické rodiny
    $xms= $c->clmn[$idp]['x_ms'];
    $u->ucasti= $xms ? "  {$xms}x" : '';
    // věk
    $vek_m= sql2stari($u->narozeni_m,$u->datum_od)?:0;
    $vek_z= sql2stari($u->narozeni_z,$u->datum_od)?:0;
    $vek= abs($vek_m-$vek_z)<5 ? $vek_m : "$vek_m/$vek_z";
    // spolu
    $spolu= '?';
    if ( $u->datsvatba ) {
      $spolu= sql2roku($u->datsvatba);
    }
    elseif ( $u->svatba ) {
      $spolu= $letos-$u->svatba;
    }
    // děti
    $deti= $u->deti;
    $sebou= 0;
    if ( $deti ) {
      $sebou= $u->_detisebou;
//      $nesebou= $deti-$sebou;
//      $deti= "$sebou+$nesebou";
      if ( $u->mindeti!='0000-00-00' && $u->maxdeti!='0000-00-00' ) {
        $deti.= "(".sql2roku($u->mindeti);
        if ( $deti>1 )
          $deti.= "-".sql2roku($u->maxdeti);
        $deti.= ")";
      }
      else
        $deti.= "(?)";
    }
    // vzdělání
    $vzdelani_muze= mb_substr($c_vzdelani[$u->vzdelani_m],0,2,"UTF-8");
    $vzdelani_zeny= mb_substr($c_vzdelani[$u->vzdelani_z],0,2,"UTF-8");
    $vzdelani= $vzdelani_muze==$vzdelani_zeny ? $vzdelani_muze : "$vzdelani_muze/$vzdelani_zeny";
//                                                         display("$vek_m/$vek_z=$vek");
    // konfese
    $cirkev= $u->cirkev_m==$u->cirkev_z
      ? ($u->cirkev_m==1 ? '' : ", {$c_cirkev[$u->cirkev_m]}")
      : ", {$c_cirkev[$u->cirkev_m]}/{$c_cirkev[$u->cirkev_z]}";
    // --------------------------------------------------------------  pro PDF
    $vps= ($u->funkce==1||$u->funkce==2 ? '* ' : ($u->_umi_vps ? '+ ' : ''));
    $key= str_pad($vzdelani_muze,2,' ',STR_PAD_LEFT).str_pad($vek_m,2,'0',STR_PAD_LEFT).$u->jmeno;
    list($prijmeni1,$etc)= explode(' ',$u->jmeno);
    if ( $etc ) $prijmeni1.= " ...";
    $result->pdf[$key]= array(
      'vps'=>$vps,'prijmeni'=>$prijmeni1,'jmena'=>"{$u->jmeno_m} a {$u->jmeno_z}",'ucasti'=>$u->ucasti);
    // --------------------------------------------------------------  pro XLS
    $majiseboudeti= $sebou ? " +$sebou" : '';
    $ucasti= $u->ucasti ? "... $u->ucasti" : 'NOVÍ';
    $r1= "$vps{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z}$majiseboudeti $ucasti";
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
    if ( $export ) {
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m,$u->jmeno,$u->_kat);
    }
  }


  if ( $export ) {
    $result->xhref= akce2_plachta_export($excel,'plachta');
    $result->xhref.= "<br><br>$err<hr>$html";
  }
end:
  $html.= $c->html;
  $result->html= "$err$html";
  return $result;
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
};
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
};
# ----------------------------------------------------------------------------- akce2 plachta_export
function akce2_plachta_export($line,$file) { trace();
  global $ezer_version;
  require_once("./ezer$ezer_version/server/licensed/xls/OLEwriter.php");
  require_once("./ezer$ezer_version/server/licensed/xls/BIFFwriter.php");
  require_once("./ezer$ezer_version/server/licensed/xls/Worksheet.php");
  require_once("./ezer$ezer_version/server/licensed/xls/Workbook.php");
  global $ezer_path_root;
  global $tisk_hnizdo;
  chdir($ezer_path_root);
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $table= "docs/$name.xls";
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
    $ws= $wb->add_worksheet("Hodnoty");
    // hlavička
    $fields= explode(',',
        'r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:4,prijmeni:12,kat:4');
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
      $ws->write_string($sy,$sx++,utf2win_sylk($x[10],true));
      $ws->write_number($sy,$sx++,$x[11]);
    }
    $wb->close();
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
    $html.= " <br>Vygenerovaným listem <b>Hodnoty</b> je třeba nahradit stejnojmenný list v sešitu";
    $html.= " <b>doc/plachta17.xls</b> a dále postupovat podle návodu v listu <b>Návod</b>.";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# ====================================================================================> . vyúčtování
# ------------------------------------------------------------------------------- akce2 sestava_noci
# generování sestavy přehledu člověkonocí pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce2_sestava_noci($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  // definice sloupců
  $result= (object)array();
  $tit= "Manželé:25,pokoj:8:r,dnů:5:r,nocí:5:r,lůžek:5:r:s,dětí 3-6:5:r:s,lůžko nocí:5:r:s,přis týlek:5:r:s,přis týlko nocí:5:r:s";
  $fld= "manzele,pokoj,pocetdnu,=noci,luzka,=deti_3_6,=luzkonoci,pristylky,=pristylkonoci";
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $cnd= $par->cnd;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $datum_od= select("datum_od","akce","id_duakce=$akce");
  $qry=  "SELECT
            ( SELECT GROUP_CONCAT(o.narozeni) FROM spolu JOIN osoba USING (id_osoba)
              WHERE id_pobyt=p.id_pobyt GROUP BY id_pobyt ) AS _naroz,
            pokoj,luzka,pristylky,pocetdnu,
            r.id_rodina,prijmeni,jmeno,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            p.pouze,r.nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9,10,13,14,15,99) AND $cond $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      $deti36= 0;
      foreach ( explode(',',$x->_naroz) as $narozeni) {
        $vek= $narozeni!='0000-00-00' ? roku_k($narozeni,$datum_od) : 0; // výpočet věku
        if ( $vek>=3 && $vek<6 )
          $deti36++;
      }

      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
        case '=deti_3_6':     $val= $deti36; break;
        case '=noci':         $val= $x->pocetdnu;
                              $exp= "=[pocetdnu,0]"; break;
//         case '=noci':         $val= max(0,$x->pocetdnu-1);
//                               $exp= "=max(0,[pocetdnu,0]-1)"; break;
        case '=luzkonoci':    $val= ($x->pocetdnu)*$x->luzka;
                              $exp= "=[=noci,0]*[luzka,0]"; break;
        case '=pristylkonoci':$val= ($x->pocetdnu)*$x->pristylky;
                              $exp= "=[=noci,0]*[pristylky,0]"; break;
        default:              $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : ($x->id_rodina ? "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}"
             : "{$x->prijmeni} {$x->jmeno}"));
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : ($x->id_rodina ? $x->nazev : $x->prijmeni));
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }

//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // doplnění počtu pečovatelů do poznámky
  $pecounu= select1("COUNT(*)","pobyt JOIN spolu USING (id_pobyt)","id_akce='$akce' AND funkce IN (99)");
  $note= $pecounu ? "K údajům v tabulce je třeba přičíst ubytování <b>$pecounu</b> pečounů<br><br>" : "";
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
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
        $style= akce2_sestava_td_style($fmts[$id]);
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
    $result->html= "$note<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------- akce2 vyuctov_pary
# generování sestavy vyúčtování pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,IF(funkce IN (10,14),3,2)),_jm";
  $result= (object)array();
  $tit= "Manželé:25"
      // . ",id_pobyt"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:s,str. pol.:5:r:s"
      . ",platba ubyt.:7:r:s,platba strava:7:r:s,platba režie:7:r:s,sleva:7:r:s,CD:6:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:s"
      . ",nedo platek:6:r:s,člen. nedo platek:6:r:s,pokladna:6:r:s,datum platby:10:s,"
      . "přepl.:6:r:s,vrátit:6:r:s,datum vratky:10:s,důvod:7,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dárce:25,dar:7:r:s,rozpočet organizace:10:r:s"
      . "";
  $fld= "=jmena"
      // . ",id_pobyt"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",platba1,platba2,platba3,platba4,=cd,=platit"
      . ",=uctem,datucet"
      . ",=nedoplatek,=prispevky,=pokladna,datpokl,"
      . "=preplatek,=vratka,datvrat,duvod,poznamka,spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,"
      . "=dotace,=nedopl,=darce,=dar,=naklad"
      . "";
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
            id_pobyt,pokoj,luzka,pristylky,kocarek,pocetdnu,
            strava_cel+strava_cel_bl+strava_cel_bm AS strava_cel,
            strava_pol+strava_pol_bl+strava_pol_bm AS strava_pol,
            platba1-vratka1 AS platba1,
            platba2-vratka2 AS platba2,
            platba3-vratka3 AS platba3,
            platba4-vratka4 AS platba4,
            -- vratka1,vratka2,vratka3,vratka4,
            -- c.ikona as pokladnou,platba,zpusobplat,datplatby,
            ( SELECT SUM(-u_castka) FROM uhrada AS u WHERE u.id_pobyt=p.id_pobyt AND u.u_stav=4) AS vratka,
            CASE funkce WHEN 14 THEN 'odhlášeni' WHEN 10 THEN 'nepřijeli' ELSE '' END AS duvod,
            ( SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3) AS uctem,
            ( SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob=3) AS pokladnou,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_zpusob!=3 AND u.u_stav!=4) AS datucet,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_zpusob=3 AND u.u_stav!=4) AS datpokl,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_stav=4) AS datvrat,
            -- SUM(IF(u_stav IN (1,2,3) AND u_zpusob=3,u_castka,0)) AS platba_pokl,
            -- SUM(IF(u_stav IN (1,2,3) AND u_zpusob!=3,u_castka,0)) AS platba_ucet,
            -- GROUP_CONCAT(DISTINCT u_datum SEPARATOR ', ') AS datplatby,
            cd,p.poznamka,r.nazev as nazev,r.spz,
            SUM(IF(t.role='d',1,0)) as _deti,
            IF(p.i0_rodina
              ,CONCAT(r.nazev,' ',GROUP_CONCAT(IF(role IN ('a','b'),o.jmeno,'') ORDER BY role SEPARATOR ' '))
              ,GROUP_CONCAT(DISTINCT CONCAT(so.prijmeni,' ',so.jmeno) SEPARATOR ' ')) as _jm,
            COUNT(dc.id_dar) AS _clenstvi,
            0+RIGHT(SUM(DISTINCT CONCAT(d.id_dar,LPAD(d.castka,10,0))),10) AS prispevky,
            GROUP_CONCAT(DISTINCT IF(t.role='a',CONCAT(so.prijmeni,' ',so.jmeno),'') SEPARATOR '') as _darce
          FROM pobyt AS p
            -- JOIN uhrada AS u USING (id_pobyt)
            JOIN spolu AS s USING (id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            JOIN osoba AS so ON so.id_osoba=s.id_osoba
            LEFT JOIN rodina AS r ON r.id_rodina=i0_rodina
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=i0_rodina
            JOIN akce AS a ON a.id_duakce=p.id_akce
            LEFT JOIN dar AS d ON d.id_osoba=s.id_osoba AND d.ukon='p' AND d.deleted=''
              AND YEAR(a.datum_do) BETWEEN YEAR(d.dat_od) AND YEAR(d.dat_do)
            LEFT JOIN dar AS dc ON dc.id_osoba=s.id_osoba AND dc.ukon='c' AND dc.deleted=''
              AND YEAR(a.datum_do)>=YEAR(dc.dat_od)
              AND (YEAR(a.datum_do) <= YEAR(dc.dat_do) OR !YEAR(dc.dat_do))
            -- JOIN _cis AS c ON c.druh='ms_akce_platba' AND c.data=zpusobplat
          WHERE p.id_akce='$akce' AND p.hnizdo=$tisk_hnizdo AND $cond AND p.funkce!=99
            -- AND p.funkce NOT IN (9,10,13,14,15,99) 
            -- AND id_pobyt IN (59318,59296,59317)
          GROUP BY id_pobyt
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.15; $DPH1_koef= 0.1304;
    $DPH2= 0.21; $DPH2_koef= 0.1736;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $platba= $x->uctem + $x->pokladnou;
        $vratka= 0 + $x->vratka;
        $preplatek= $platba > $predpis ? $platba - $predpis : 0;
        $nedoplatek= $platba < $predpis ? $predpis - $platba : 0;
//        $preplatek= $x->platba > $predpis ? $x->platba - $predpis : '';
//        $nedoplatek= $x->platba < $predpis ? $predpis - $x->platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]"; break;
        case '=preplatek':  $val= $preplatek ?: '';
                            $exp= "=IF([=pokladna,0]+[=uctem,0]>[=platit,0],[=pokladna,0]+[=uctem,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek ?: '';
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=uctem':      $val= 0+$x->uctem; break;
//        case '=uctem':      $val= $x->pokladnou ? '' : 0+$x->platba; break;
//        case '=datucet':    $val= $x->pokladnou ? '' : $x->datplatby; break;
        case '=pokladna':   $val= 0+$x->pokladnou; break;
//        case '=datpokl':    $val= $x->pokladnou ? $x->datplatby : ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        // nedoplatek členského příspěvku činného člena
        case '=prispevky':  $val= ($x->_clenstvi && $x->prispevky!=100*$x->_clenstvi 
                                ? 100*$x->_clenstvi-$x->prispevky : '-'); break;
        case '=ubyt':       $val= round($x->platba1 - $x->platba1*$DPH1_koef);
                            $exp= "=[platba1,0]-[=ubytDPH,0]"; break;
        case '=ubytDPH':    $val= round($x->platba1*$DPH1_koef);
                            $exp= "=ROUND([platba1,0]*$DPH1_koef,0)"; break;
        case '=strava':     $val= round($x->platba2 - $x->platba2*$DPH2_koef);
                            $exp= "=[platba2,0]-[=stravaDPH,0]"; break;
        case '=stravaDPH':  $val= round($x->platba2*$DPH2_koef);
                            $exp= "=ROUND([platba2,0]*$DPH2_koef,0)"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=vratka':     $val= $vratka; break;
        case '=zaplaceno':  $val= 0+$platba-$vratka;
                            $exp= "=[=uctem,0]+[=pokladna,0]-[=vratka,0]"; break;
//        case '=zaplaceno':  $val= 0+$x->platba;
//                            $exp= "=[=uctem,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=darce':      $val= $preplatek-$vratka ? "dar - {$x->_darce}" : ''; break;
        case '=dar':        $val= $preplatek-$vratka;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad;
                            $exp= "=[=platit,0]-[platba4,0]"; break;
        case '=jmena':      $val= $x->_jm; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        $val= $f ? $x->$f : '';
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) && is_numeric($val) ) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->DPH= array(
      "základ","=[=ubyt,s]+[=strava,s]+[=rezie,s]"
     ,"DPH ".($DPH2*100)."%","=[=stravaDPH,s]"
     ,"DPH ".($DPH1*100)."%","=[=ubytDPH,s]"
     ,"předpis celkem","=[=ubyt,s]+[=strava,s]+[=rezie,s]+[=stravaDPH,s]+[=ubytDPH,s]"
   );
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
        $style= akce2_sestava_td_style($fmts[$id]);
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------ akce2 vyuctov_pary2
# generování sestavy vyúčtování pro účastníky $akce - bez DPH, zato se zvláštími platbami dětí
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Jméno:25"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:s,str. pol.:5:r:s"
      . ",poplatek dospělí:8:r:s"
      . ",na účet:7:r:s,datum platby:10:r"
      . ",poplatek děti:8:r:s,na účet děti:7:r:s,datum platby děti:10:r"
      . ",nedo platek:7:r:s,pokladna:6:r:s,přepl.:6:r:s,poznámka:50,SPZ:9"
      . ",rozpočet dospělí:10:r:s,rozpočet děti:10:r:s"
      . "";
  $fld= "=jmena"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",=platit,platba,datplatby"
      . ",poplatek_d,platba_d,datplatby_d"
      . ",=nedoplatek,=pokladna,=preplatek,poznamka,spz"
      . ",=naklad,naklad_d"
      . "";
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT id_pobyt,
          poplatek_d,naklad_d, -- platba_d,datplatby_d
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,
          strava_cel+strava_cel_bl+strava_cel_bm AS strava_cel,
          strava_pol+strava_pol_bl+strava_pol_bm AS strava_pol,
          platba1,platba2,platba3,platba4,
            IFNULL((SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3 AND u_za=0),0) AS platba,
            IFNULL((SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3 AND u_za=1),0) AS platba_d,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u_datum!='0000-00-00' AND u_zpusob!=3 AND u_stav!=4 AND u_za=0) AS datplatby,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u_datum!='0000-00-00' AND u_zpusob!=3 AND u_stav!=4 AND u_za=1) AS datplatby_d,
          cd,p.poznamka, -- platba,datplatby,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
          IF(p.i0_rodina
            ,CONCAT(r.nazev,' ',GROUP_CONCAT(IF(role IN ('a','b'),o.jmeno,'') ORDER BY role SEPARATOR ' '))
            ,GROUP_CONCAT(DISTINCT CONCAT(o.prijmeni,' ',o.jmeno) SEPARATOR ' ')) as _jm,
          SUM(IF(t.role='d',1,0)) as _deti,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o USING (id_osoba) 
          LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,id_rodina)
          LEFT JOIN tvori AS t USING (id_osoba,id_rodina) 
          WHERE p.id_akce='$akce' AND p.hnizdo=$tisk_hnizdo AND p.funkce NOT IN (9,10,13,14,15,99) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord
          -- LIMIT 3
      ";
//   $qry.=  " LIMIT 10";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
//     $DPH1= 0.1;
//     $DPH2= 0.2;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $predpis_d= $predpis + $x->poplatek_d;
        $platba= $x->platba + $x->platba_d;
        $preplatek= $platba > $predpis_d ? $platba - $predpis_d : '';
        $nedoplatek= $platba < $predpis_d ? $predpis_d - $platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
//                             $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]";
                            break;
        case '=preplatek':  $val= $preplatek;
                            $exp= "=IF([platba,0]+[platba_d,0]>[=platit,0]+[poplatek_d,0]"
                                . ",[platba,0]+[platba_d,0]-[=platit,0]-[poplatek_d,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek;
                            $exp= "=IF([platba,0]+[platba_d,0]<[=platit,0]+[poplatek_d,0]"
                                . ",[=platit,0]+[poplatek_d,0]-[platba,0]-[platba_d,0],0)"; break;
        case '=pokladna':   $val= ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
//         case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
//                             $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
//         case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
//                             $exp= "=[platba1,0]-[=ubyt,0]"; break;
//         case '=strava':     $val= round($x->platba2/(1+$DPH2));
//                             $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
//         case '=stravaDPH':  $val= round($x->platba2*$DPH2/(1+$DPH2));
//                             $exp= "=[platba2,0]-[=strava,0]"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=zaplaceno':  $val= 0+$x->platba;
                            $exp= "=[platba,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
//         case '=nedopl':     $val= $nedoplatek;
//                             $exp= "=IF([platba,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=dar':        $val= $preplatek;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad; break;
        case '=jmena':      $val= $x->_jm; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        $val= $f ? $x->$f : '';
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) && is_numeric($val)) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->X= array(
      "Přehled dotace"
      ,"dotace dospělí","=[=naklad,s]-[=platit,s]"
      ,"dotace děti","=[naklad_d,s]-[poplatek_d,s]"
   );
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
        $style= akce2_sestava_td_style($fmts[$id]);
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# =====================================================================================> . XLS tisky
# ---------------------------------------------------------------------------------- tisk2 vyp_excel
# generování tabulky do excelu
# tab.tits = názvy sloupců
# tab.flds = názvy položek
# tab.clmn = hodnoty položek
# tab.atrs = formáty
# tab.expr = vzorce
#    .DPH, .X = specifické tabulky
function tisk2_vyp_excel($akce,$par,$title,$vypis,$tab=null,$hnizdo=0) {  trace();
  global $xA, $xn, $tisk_hnizdo, $ezer_version;
  $tisk_hnizdo= $hnizdo;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= tisk2_sestava($akce,$par,$title,$vypis,true,$hnizdo);
//                                                    debug($tab,"tisk2_vyp_excel/tab");
  // nová hlavička
  $Z= Excel5_n2col(count($tab->flds)-1);
  list($a_org,$a_misto,$a_druh,$a_od,$a_do,$a_kod)= 
      select("access,misto,IFNULL(zkratka,''),datum_od,datum_do,ciselnik_akce", //a IFNULL(g_kod,'')
        "akce "
//a          . "LEFT JOIN join_akce ON id_akce=id_duakce "
          . "LEFT JOIN _cis ON _cis.druh='ms_akce_typ' AND data=akce.druh",
        "id_duakce=$akce");
  $a_co= ($a_org==1?'YMCA Setkání, ':($a_org==2?'YMCA Familia, ':'')).$a_druh;
  $a_oddo= datum_oddo($a_od,$a_do);
  $a_celkem= count($tab->clmn);
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title          $a_kod::bold size=14 |A2 $vypis ::bold size=12
    |{$Z}1 $a_co ::bold left size=14
    |{$Z}2 $a_misto, $a_oddo ::bold size=14 left
    |A3 Celkem: $a_celkem ::bold
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
    list($id,$w,$f,$s)= array_merge(explode(':',$idw),array_fill(0,4,''));      // název sloupce : šířka : formát : suma
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
          case 't':                      $format.= ' text'; break;
          }
        }
      }
      if (isset($tab->atrs[$i][$id]) ) {
        // buňka má nastavený formát
        $format.= ' '.$tab->atrs[$i][$id];
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
  if ( isset($tab->DPH) ) {
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
  if ( isset($tab->X) ) {
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
  $n+= 4;
  $xls.= "|A$n Výpis byl vygenerován $kdy :: italic";
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//                                                                display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xlsx' target='xlsx'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- akce_vyp_subst
function akce_vyp_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("akce_vyp_excel: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
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
# =====================================================================================> . PDF tisky
# ----------------------------------------------------------------------------------- tisk2 pdf_mrop
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk2_pdf_mrop($akce,$par,$title,$vypis,$report_json) {  trace(); debug($par,'tisk2_pdf_mrop');
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk2_sestava($akce,$par,$title,$vypis,true);
//                                         display($report_json);
//                                        debug($tab,"tisk2_sestava($akce,...)"); //return;
//   $report_json= "
//   {'format':'A4:5,6,70,41','boxes':[
//   {'type':'text','left':2.6,'top':11,'width':60,'height':27.3,'id':'jmeno','txt':'{pr_jm}','style':'16,C'},
//   {'type':'text','left':10,'top':20,'width':15,'height':10,'id':'$100','txt':'skupina','style':'8,C'},
//   {'type':'text','left':10,'top':25,'width':15,'height':20,'id':'skupina','txt':'{skupina}','style':'14,C'},
//   {'type':'text','left':40,'top':20,'width':10,'height':10,'id':'$101','txt':'chata','style':'8,C'},
//   {'type':'text','left':40,'top':25,'width':10,'height':20,'id':'chata','txt':'{chata}','style':'14,C'}]}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $parss[$n]->jmena=   strtr($x->jmena,array(','=>'<br>'));
    $parss[$n]->pr_jm=   $x->pr_jm;
    $parss[$n]->chata=   $x->pokoj;
    $parss[$n]->skupina= $x->skupina;
    $parss[$n]->jmeno=   $x->jmeno;
    $parss[$n]->prijmeni=$x->prijmeni;
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ------------------------------------------------------------------------------- tisk2 pdf_jmenovky
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk2_pdf_jmenovky($akce,$par,$title,$vypis,$report_json,$hnizdo) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk2_sestava($akce,$par,$title,$vypis,true,$hnizdo);
//                                         display($report_json);
//                                         debug($tab,"tisk2_sestava($akce,...)"); //return;
  $report_json= 
    '{"format":"A4:15,10,90,55",
      "boxes":[
        {"type":"text",
         "left":0,"top":0,"width":90,"height":55,
         "id":"ram","style":"1,L,LTRB:0.4 dotted","txt":" "
        },
        {"type":"text",
         "left":10,"top":10,"width":80,"height":40,
         "id":"jmeno","txt":"{jmeno}<br>{prijmeni}","style":"30,L"
        }
      ]
    }';
//                                            display($report_json);
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $fsize= mb_strlen($x->jmeno)>9 ? 12 : (mb_strlen($x->jmeno)>8 ? 13 : 14);
    $parss[$n]->jmeno= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$x->jmeno}</span>";
    $prijmeni= $x->prijmeni;
//     list($prijmeni)= explode(' ',$x->prijmeni);
    $fsize= mb_strlen($prijmeni)>10 ? 10 : 12;
    $parss[$n]->prijmeni= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$prijmeni}</span>";
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# --------------------------------------------------------------------------------- akce2 pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function akce2_pdf_stitky($akce,$par,$report_json,$hnizdo) { trace();
  global $ezer_path_docs, $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  $ret= (object)array('_error'=>0,'html'=>'testy');
  $par->fld= "prijmeni,rodice,ulice,psc,obec,stat";
  // projdi požadované adresy rodin
  $tab= tisk2_sestava_pary($akce,$par,'PDF','$vypis',true);
//                                                         debug($par);
//                                                         debug($tab->clmn); //goto end;
  $parss= array(); $n= 0;
  foreach ($tab->clmn as $x) {
    $jmena= $x['rodice'];
    $ulice= str_replace('®','',$x['ulice']);
    $psc=   str_replace('®','',$x['psc']);
    $obec=  str_replace('®','',$x['obec']);
    $stat=  str_replace('®','',$x['stat']);
    $stat= $stat=='CZ' ? '' : $stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= $jmena;
    $parss[$n]->adresa_postovni= "$ulice<br/>$psc  $obec".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
//                                                         debug($parss);
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $ret->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
end:
  return $ret;
}
/*
# --------------------------------------------------------------------------------- akce2 pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function xxx_akce2_pdf_stitky($cond,$report_json) { trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  // projdi požadované adresy rodin
  $n= 0;
  $parss= array();
  $qry=  "SELECT
          r.nazev as nazev,p.pouze as pouze,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
          r.ulice,r.psc,r.obec,r.stat,r.telefony,r.emaily,p.poznamka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE $cond
          GROUP BY id_pobyt
          ORDER BY IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    // formátované PSČ (tuzemské a slovenské)
    $psc= (!$x->stat||$x->stat=='CZ'||$x->stat=='SK')
      ? substr($x->psc,0,3).' '.substr($x->psc,3,2)
      : $x->psc;
    $stat= $x->stat=='CZ' ? '' : $x->stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= "{$x->jmena} {$x->prijmeni}";
    $parss[$n]->adresa_postovni= "{$x->ulice}<br/>$psc  {$x->obec}".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
*/
# --------------------------------------------------------------------------------- tisk2 pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
# pokud jsou iregularity strav a dietní stravy, generuje se i přetokový soubor
function tisk2_pdf_prijem($akce,$par,$stitky_json,$popis_json,$hnizdo) {  trace();
  global $diety,$diety_,$jidlo_,$tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $popisy= false;
  $html= '';
  // získání dat
  $tab= tisk2_sestava_pary($akce,$par,'$title','$vypis',true);
//                                         debug($tab,"tisk2_sestava_pary($akce,...)"); //return;
  // projdi vygenerované záznamy
  $n= $n2= 0;
  $parss= $parss2= array();
  // omezení pro testy
//   foreach ( $tab->clmn as $i=>$xa ) { // reprezentatnti
//     if ( strpos('Zelinkovi',$xa['prijmeni'])===false )
//     if ( strpos('BáčoviBarotoviDrvotoviZelinkovi',$xa['prijmeni'])===false )
//     unset($tab->clmn[$i]);
//   }
//                                                 debug($tab->clmn); //goto end;
  foreach ( $tab->clmn as $xa ) {
    $idp= $xa['^id_pobyt'];
    $x= (object)$xa;
    $_diety= $x->strava_cel_bm!=0  || $x->strava_pol_bm!=0 
          || $x->strava_cel_bl!=0  || $x->strava_pol_bl!=0;
    // výpočet strav včetně přetokového souboru na iregularity a diety
    if ( $x->_vyjimky || $_diety ) {
      $par->souhrn= 1;
//      $ret= akce2_strava_pary($akce,$par,'','',0,$idp);
//                                                 debug($ret,"akce2_strava_pary");
      $ret= akce2_strava($akce,(object)array(),'','',true,0,$idp);
//                                                 debug($ret,"akce2_strava");
      if ( !$x->_vyjimky ) {
        // pravidelná strava s dietami
        $strava= "strava: ";
        if ( $x->strava_cel || $x->strava_pol )
          $strava.= " <b>{$x->strava_cel}/{$x->strava_pol}</b>";
        if ( $x->strava_cel_bm || $x->strava_pol_bm )
          $strava.= " veget. <b>{$x->strava_cel_bm}/{$x->strava_pol_bm}</b>";
        if ( $x->strava_cel_bl || $x->strava_pol_bl )
          $strava.= " bezlep. <b>{$x->strava_cel_bl}/{$x->strava_pol_bl}</b>";
      }
      else { // $x->_vyjimky
        // nepravidelná strava příp. žádná ... počítáme z vydaných stravenek, pole dieta ignorujeme
        $popisy= true;
        $h= tisk2_pdf_prijem_ireg("{$x->prijmeni}: {$x->jmena}",$x,$ret,$par);
//                                                display("$h");
        if ( $h ) {
          $strava= "strava: viz popis v obálce";
          $parss2[$n2]= (object)array();
          $parss2[$n2]->tab= $h;
          $n2++;
        }
        else {
          $strava= "strava: neobjednána";
        }
      }
    }
    else {
      // normální pravidelná strava (bez diet)
      $strava= $x->strava_cel || $x->strava_pol ? ( "strava: "
             . ($x->strava_cel?"celá <b>{$x->strava_cel}</b> ":'')
             . ($x->strava_pol?"poloviční <b>{$x->strava_pol}</b>":'')) : "bez stravy";
    }
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->line1= "<b>{$x->prijmeni}: {$x->jmena}</b>";
    $parss[$n]->line2= ($x->pokoj?"pok. <b>{$x->pokoj}</b> ":'')
                     . ($x->skupina?"skup. <b>{$x->skupina}</b>":'');
    $parss[$n]->line3= $x->luzka || $x->pristylky || $x->kocarek ? (
                       ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                     . ($x->pristylky?"přistýlky <b>{$x->pristylky} </b>":'')
                     . ($x->kocarek?"kočárek <b>{$x->kocarek}</b>":'')
                       ) : "bez ubytování";
    $parss[$n]->line4= $strava;
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($stitky_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  if ( $popisy ) {
    $fname2= 'popisy_'.date("Ymd_Hi");
    $fpath2= "$ezer_path_docs/$fname2.pdf";
    $err= dop_rep_ids($popis_json,$parss2,$fpath2);
    $result->html.= $err ? $err
      : " a doplněn popisy do obálek ve formátu <a href='docs/$fname2.pdf' target='pdf'>PDF</a>.";
  }
end:
  return $result;
}
# ------------------------------------------------------- tisk2 pdf_prijem_ireg
# generování tabulky s nepravidelnou stravou
# pokud jsou snídaně jen celé (snidane=c) píší se do jediného sloupce
function tisk2_pdf_prijem_ireg($nazev,$x,$ret,$par) {  trace();
  global $diety,$diety_,$jidlo_;
  $jidel= 0;
  $jidla= explode(',',$par->snidane=='c' ? 'sc,oc,op,vc,vp' : 'sc,sp,oc,op,vc,vp');
  $s= " style=\"background-color:#DDDDDD\"";
  $h= "<h3>$nazev</h3>";
  $h.= '<small><table border="1" cellpadding="2" cellspacing="0" align="center">';
  // nadhlavička
  $chodu= $par->snidane=='c' ? 5 : 6;
  $h.= "<tr><th$s>dieta:</th>";
  foreach ($diety as $dieta) {
    $h.= "<th$s colspan=\"$chodu\">{$diety_[$dieta]}</th>";
  }
  $h.= "</tr>";
  // hlavička
  $h.= "<tr><th$s>den</th>";
  foreach ($diety as $dieta) {
    foreach ($jidla as $jidlo) {
      $chod= $jidlo=='sc' && $par->snidane=='c' ? 'snídaně' : $jidlo_[$jidlo];
      $h.= "<th$s>$chod</th>";
    }
  }
  $h.= "</tr>";
  // dny
  foreach ($ret->days as $day) {
    $h.= "<tr><th$s>$day</th>";
    foreach ($diety as $dieta) {
      foreach ($jidla as $jidlo) {
        $fld= "$day $jidlo$dieta";
        $suma= $ret->suma[$fld];
        if ( $jidlo=='sc' && $par->snidane=='c' ) {
          // pokud jsou snídaně jen celé, přičti objednávky polovičních
          $fld= "{$day}sp $dieta";
          $suma+= $ret->suma[$fld];
        }
        $pocet= $suma ?: '';
        $jidel+= $suma;
        $h.= "<td>$pocet</td>";
      }
    }
    $h.= "</tr>";
  }
  // konec
  $h.= "</table></small>";
  return $jidel ? $h : ''; // "<h3>$nazev</h3> bez stravy";
}
# -------------------------------------------------------------------------------- tisk2 pdf_plachta
# generování štítků se jmény párů
# mezery= řádků;i1+x1,i2+x2,... znamená, že po i-tém štítku bude x prázdných (i je výsledný index)
function tisk2_pdf_plachta($akce,$report_json=0,$hnizdo=0,$_mezery='') {  trace();
  global $ezer_path_docs,$tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  setlocale(LC_ALL, 'cs_CZ.utf8');
  $result= (object)array('_error'=>0,'html'=>'?');
//  $_mezery= "4+1";
  $mezery= array();
  $radku= 14;
  if ($_mezery) {
    list($radku,$ixs)= explode(';',$_mezery);
    $ixs= explode(',',$ixs);
    foreach ($ixs as $ix) {
      list($i,$x)= explode('+',$ix);
      $mezery[$i]= $x;
    }
  }
//                                          debug($mezery,'mezery');
  $html= '';
  $A= 'A';
  $n= 1;
  $i= 0;
  // získání dat
  $tab= akce2_plachta($akce,'$par','$title','$vypis',0,$hnizdo);
  if (!isset($tab->pdf)) return $result;
  unset($tab->xhref);
  unset($tab->html);
//  ksort($tab->pdf,SORT_LOCALE_STRING);
//                                               debug($tab->pdf);

    foreach ( $tab->pdf as $par=>$xa ) {
      // započtení mezer, předaných přes $_mezery
      if (isset($mezery[$i])) $i+= $mezery[$i];
      $Ai= $i%$radku;
      $ni= ceil(($i+1)/$radku);
      $tab->pdf[$par]['a1']= chr(ord('A')+$Ai).$ni;
      $i++;
    }
    $result= tisk2_table(array('příjmení','jména','a1','účasti'),array('prijmeni','jmena','a1','ucasti'),$tab->pdf);


  // projdi vygenerované záznamy
  $n= 0;
  $i= 0;
  if ( $report_json) {
    $parss= array();
    foreach ( $tab->pdf as $par=>$xa ) {
      // započtení mezer, předaných přes $_mezery
      if (isset($mezery[$i])) $i+= $mezery[$i];
      $Ai= $i%$radku;
      $ni= ceil(($i+1)/$radku);
      $A1= chr(ord('A')+$Ai).$ni;
      $tab->pdf[$par]['a1']= $A1;
      $i++;
      // definice pole substitucí
      $x= (object)$xa;
      $parss[$n]= (object)array();  // {prijmeni}<br>{jmena}
      $prijmeni= $x->prijmeni;
      $ucasti= trim($x->ucasti);
      $len= mb_strlen($prijmeni);
      $xlen= round(tc_StringWidth($prijmeni,'B',15));
      $fs= 20;
      if ( in_array($prijmeni,array("Beszédešovi","Stanislavovi")) )
                          {     $fw= 'ultra-condensed'; }
      elseif ( $xlen<20 ) {     $fw= 'condensed'; }
      elseif ( $xlen<27 ) {     $fw= 'condensed'; }
      elseif ( $xlen<37 ) {     $fw= 'extra-condensed'; }
      else {                    $fw= 'ultra-condensed'; }
                                                display("$prijmeni ... $xlen / $fw");

      $s1= "font-stretch:$fw;font-size:{$fs}mm;font-weight:bold;text-align:center";
      $bg1= $x->vps=='* ' ? "background-color:gold" : ($x->vps=='+ ' ? "background-color:lightblue" : '');
      $s2= "font-size:5mm;text-align:center";
      $bg2= '';
      $s3= "font-size:8mm;font-weight:bold;text-align:right";
      $bg3= !$ucasti ? "background-color:lightgreen" : (
        $ucasti=='1x' || $ucasti=='2x' ? "background-color:orange"
        : "background-color:silver");
      $parss[$n]->prijmeni= "<span style=\"$s1;$bg1\">$prijmeni</span>";
      $parss[$n]->jmena= "<span style=\"$s3;$bg3\">$A1</span>&nbsp;&nbsp;&nbsp;&nbsp;"
                       . "<span style=\"$s2;$bg2\"><br>{$x->jmena}</span>";
      $n++;
    }
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    $err= dop_rep_ids($report_json,$parss,$fpath);
    $html= $err ? $err
      : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
    $result->html= $html.$result->html;
  }
  else {
//    foreach ( $tab->pdf as $par=>$xa ) {
//      // započtení mezer, předaných přes $_mezery
//      if (isset($mezery[$i])) $i+= $mezery[$i];
//      $Ai= $i%$radku;
//      $ni= ceil(($i+1)/$radku);
//      $tab->pdf[$par]['a1']= chr(ord('A')+$Ai).$ni;
//      $i++;
//    }
//                                                     debug($tab->pdf);
//    $result= tisk2_table(array('příjmení','jména','a1','účasti'),array('prijmeni','jmena','a1','ucasti'),$tab->pdf);
  }
end:
  return $result;
}
# ------------------------------------------------------------------------------ akce2 pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí tisk2_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce2_pdf_stravenky($akce,$par,$report_json,$hnizdo) {  trace();
  $res_all= (object)array('_error'=>0);
  $res_all->html= "<br>Stravenky jsou v souborech: ";
  // získání dat
  $res_vse= tisk2_sestava($akce,$par,'$title','$vypis',true,$hnizdo);
  foreach ($res_vse->res as $x) {
//                                                         if ( $x->dieta != '_bl' ) continue;
    $x->nazev= $x->nazev_diety=='normální' ? '' : $x->nazev_diety;
    $res= akce2_pdf_stravenky_dieta($x,$par,$report_json,$hnizdo);
    if ( $res->_error )
      fce_warning("{$x->nazev_diety} - {$res->_error}");
    else
      $res_all->html.= " {$res->href} - strava {$x->nazev_diety}, ";
  }
  return $res_all;
}
# ------------------------------------------------------------------------ akce2 pdf_stravenky_dieta
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí tisk2_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce2_pdf_stravenky_dieta($x,$par,$report_json,$hnizdo) {  trace();
//                                                 debug($x,"akce2_pdf_stravenky_dieta");
// function akce2_pdf_stravenky_dieta($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
//   $x= tisk2_sestava($akce,$par,$title,$vypis,true);
//  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia"; // moc dlouhé
  $org= "YMCA";
  if (isset($x->akce->hnizda) && $hnizdo>0) {
    $hnizda= explode(',',$x->akce->hnizda);
    $header= "$org, {$hnizda[$hnizdo-1]} {$x->akce->rok}";
  }
  else {
    $misto= isset($x->akce->misto) ? $x->akce->misto : '';
    $rok= isset($x->akce->rok) ? $x->akce->rok : '';
    $header= "$org, $misto $rok";
  }
  $sob= array('s'=>'snídaně','o'=>'oběd','v'=>'večeře');
  $cp=  array('c'=>'1','p'=>'1/2');
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $x->tab as $jmeno=>$dny ) {
    // zjistíme, zda nějaké stravenky má - pokud ne, řádek netiskneme
    $ma= 0;
    foreach ( $dny as $den=>$jidla ) {
      foreach ( $jidla as $jidlo=>$porce ) {
        foreach ( $porce as $velikost=>$pocet ) {
          $ma+= $pocet;
        }
      }
    }
    if ( !$ma ) continue;
    // vynechání prázdných míst, aby jméno bylo v prvním sloupci ze 4
    $k= 4*ceil($n/4)-$n;
    for ($i= 0; $i<$k; $i++) {
      $parss[$n]= (object)array();
      $parss[$n]->header= $parss[$n]->line1= $parss[$n]->line2= $parss[$n]->line3= '';
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
    $parss[$n]->line3= '';
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
              $parss[$n]->line3= '';
              $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
              $n++;
            }
            // text stravenky na jedno jídlo
            $jid= $sob[$jidlo];
            $parss[$n]= (object)array();
            $parss[$n]->header= $header;
            $parss[$n]->line1= "$den";
            $parss[$n]->line2= "<b>$jid</b>";
            $parss[$n]->line3= "<small>{$x->nazev}</small>";
            if ( $velikost=='c' 
              || $jid=='snídaně' && isset($par->snidane) && $par->snidane=='c' ) {
              // celá porce
              $parss[$n]->ram= '<img src="db2/img/stravenky-rastr-2.png"'
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
    $parss[$n]->line3= " ";
    $parss[$n]->rect= $parss[$n]->ram= '';
    $parss[$n]->end= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
//                                         debug($report_json,"report");
//                                         return $result;
  $fname= "stravenky{$x->dieta}_".date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  if ( $err )
    $result->_error= $err;
  else
    $result->href= "<a href='docs/$fname.pdf' target='pdf'>PDF{$x->dieta}</a>";
  return $result;
}
# ----------------------------------------------------------------------------- akce2 pdf_stravenky0
# generování stránky stravenek pro ruční vyplnění do PDF
function akce2_pdf_stravenky0($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat o akci
  $qa="SELECT nazev, YEAR(datum_od) AS akce_rok, misto
       FROM akce WHERE id_duakce='$akce' ";
  $ra= pdo_qry($qa);
  $a= pdo_fetch_object($ra);
  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia";
  $header= "$org, {$a->misto} {$a->akce_rok}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  $pocet= 4*12;
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= ""; //$den";
    $parss[$n]->line2= "";
    $parss[$n]->line3= "";
    $parss[$n]->rect=  "";
    $parss[$n]->end= '';
    $parss[$n]->ram= '<img src="db2/img/stravenky-rastr-2.png" style="width:48mm;height:23mm" border="0" />';
    $n++;
  }
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= ""; //$den";
    $parss[$n]->line2= "";
    $parss[$n]->line3= "";
    $parss[$n]->rect=  "<b>1/2</b>";
    $parss[$n]->end= '';
    $parss[$n]->ram= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce2_pdf_stravenky0");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
//                                         return $result;
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------------- dop rep_ids
# LOCAL
# vytvoření dopisů se šablonou pomocí TCPDF podle parametrů
# $parss  - pole obsahující substituce parametrů pro $text
# vygenerované dopisy ve tvaru souboru PDF se umístí do ./docs/$fname
# případná chyba se vrátí jako Exception
function dop_rep_ids($report_json,$parss,$fname) { trace();
//  global $json;
  $err= 0;
  // transformace $parss pro strtr
  $subst= array();
  for ($i=0; $i<count($parss); $i++) {
    $subst[$i]= array();
    foreach($parss[$i] as $x=>$y) {
      $subst[$i]['{'.$x.'}']= $y;
    }
  }
  $report= json_decode(str_replace("'",'"',$report_json));
  if ( json_last_error() ) {
    $err= json_last_error_msg();
    display($err);
  }
//                                                         debug($report,"dop_rep_ids");
  // vytvoření $texty - seznam
  $texty= array();
  for ($i=0; $i<count($parss); $i++) {
    $texty[$i]= (object)array();
    foreach($report->boxes as $box) {
      $id= $box->id;
      if ( !$id) fce_error("dop_rep_ids: POZOR: box reportu musí být pojmenován");
      $texty[$i]->$id= strtr($box->txt,$subst[$i]);
    }
  }
//                                                         debug($texty,'dop_rep_ids');
//                                                         return null;
  try {
    tc_report($report,$texty,$fname);
  }
  catch (Exception $e) {
    $err= $e->getMessage();
  }
  return $err;
}
/** =========================================================================================> EVID2 */
# -------------------------------------------------------------------------------- evid2 deti_access
# ==> . chain rod
# účastníci pobytu a případná rodina budou mít daný access (3)
function evid2_deti_access($idr,$access=3) {
  // zjištění access rodičů
  list($min,$max)= select("MIN(o.access),MAX(o.access)",
                          "rodina JOIN tvori USING (id_rodina) JOIN osoba AS o USING (id_osoba)",
                          "id_rodina=$idr AND role IN ('a','b') GROUP BY id_rodina");
  if ( $min==$access && $max==$access ) {
    // změna dětí v rodině
    $qo= pdo_qry("SELECT access,id_osoba FROM tvori JOIN osoba USING (id_osoba)
                    WHERE id_rodina=$idr AND role='d' ");
    while ( $qo && ($o= pdo_fetch_object($qo)) ) {
      $ido= $o->id_osoba;
      $o_access= $o->access;
      if ( $o_access!=$access ) {
        ezer_qry("UPDATE",'osoba',$ido,array(
          (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o_access)
        ));
      }
    }
    // změna pro rodinu
    $r_access= select("access","rodina","id_rodina=$idr");
    if ( $r_access!=$access ) {
      ezer_qry("UPDATE",'rodina',$idr,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
      ));
    }
  }
  return 1;
}
# ---------------------------------------------------------------------------------- evid2 elim_tips
# tipy na duplicitu ve formě CASE ... END, type= 
#   mrop - pro iniciované muže
#   mail - lidi se stejným meilem
function evid2_elim_tips($type) {
  $ret= (object)array('ids'=>0,'tip'=>"''");
  if ($type=='mail') {
    $ret= evid2_elim_mail_tips();
    goto end;
  }
  switch ($type) {
  case 'mrop': $qry= "
      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
      FROM osoba AS o
      JOIN osoba AS d USING (prijmeni,jmeno,narozeni)
      WHERE o.iniciace>0 AND d.iniciace=0 
        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
    ";
    break;
  case 'narozeni': $qry= "
      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
      FROM osoba AS o
      JOIN osoba AS d USING (prijmeni,jmeno,narozeni)
      WHERE o.narozeni!='0000-00-00' AND o.prijmeni!='' AND o.jmeno NOT IN ('','???')
      --  AND o.iniciace=0 AND d.iniciace=0 
        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
    ";
    break;
  case 'prijmeni': $qry= "
      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
      FROM osoba AS o
      JOIN osoba AS d USING (prijmeni,jmeno)
      WHERE o.narozeni!='0000-00-00' AND o.prijmeni!='' AND o.jmeno NOT IN ('','???')
      --  AND o.iniciace=0 AND d.iniciace=0 
        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
    ";
    break;
  case 'mail': $qry= "
      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _maily
      FROM osoba AS o
      JOIN osoba AS d USING (email)
      WHERE o.kontakt=1 AND d.kontakt=1 AND o.email!='' 
        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
      GROUP BY o.email HAVING LOCATE(',',_maily)>0    
    ";
    break;
  case 'telefon': $qry= "
      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _telefony
      FROM osoba AS o
      JOIN osoba AS d USING (telefon)
      WHERE o.kontakt=1 AND d.kontakt=1 AND o.telefon!='' 
        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
      GROUP BY o.telefon HAVING LOCATE(',',_telefony)>0    ";
    break;
  }
  if ( !$qry ) goto end;
  // vlastní prohledání
  $ids= ""; $del= "(";
  $tip= "";
  $zs= pdo_qry($qry);
  while ($zs && (list($id,$tips)= pdo_fetch_row($zs))) {
    $ids.= "$del $id,$tips"; $del= ",";
    $tip.= " WHEN $id THEN '$tips'";
    foreach (explode(',',$tips) as $tp) {
      $tip.= " WHEN $tp THEN '$id'";
    }
  }
  $ret->ids= $ids ? "o.id_osoba IN $ids )" : '0';
  $ret->tip= $tip ? "CASE id_osoba $tip ELSE 0 END" : 0;
end:
  return $ret;
}
# ---------------------------------------------------------------------------------- evid2 elim_tips
# tipy na duplicitu mailů - vrací seznam
#   mail - lidi se stejným mailem
function evid2_elim_mail_tips() {
  $ret= (object)array('ids'=>0,'tip'=>"''");
  $m_os= [];
  $zs= pdo_qry("SELECT id_osoba,email FROM osoba WHERE kontakt=1 AND email!='' AND deleted='' "
//      . "AND prijmeni='Červeň'"
      . "ORDER BY id_osoba ");
  while ($zs && (list($ido,$mails)= pdo_fetch_row($zs))) {
    foreach (preg_split('/\s*[,;]\s*/',trim($mails," \n\r\t;,#")) as $m) {
      if (!isset($m_os[$m])) 
        $m_os[$m]= [$ido];
      else
        $m_os[$m][]= $ido;
    }
  }
  // projdeme duplicity
  $n= 0;
  $ids= ""; $del= "(";
  $tip= "";
  foreach ($m_os as $m=>$os) {
    if (count($os)>1) {
      $n++;
      $ids.= $del.implode(',',$os); $del= ",";
      foreach ($os as $tp) {
        $tip.= " WHEN $tp THEN $n ";
      }
    }
  }
  display("CELKEM $n duplicit");
  $ret->ids= $ids ? "o.id_osoba IN $ids )" : '0';
  $ret->tip= $tip ? "CASE id_osoba $tip ELSE 0 END" : 0;
  return $ret;
}
# ------------------------------------------------------------------------------------- evid2 delete
# zjistí, zda lze osobu smazat: dar, platba, spolu, tvori
# cmd= conf_oso|conf_rod|del_oso|del_rod
function evid2_upd_gmail($id_osoba,$orig,$gmail) { trace();
  ezer_qry("UPDATE",'osoba',$id_osoba,array(
    (object)array('fld'=>'gmail', 'op'=>'u','val'=>$gmail,'old'=>$orig)
  ));
  return 1;
}
# ------------------------------------------------------------------------------------- evid2 delete
# zjistí, zda lze osobu smazat: dar, platba, spolu, tvori
# cmd= conf_oso|conf_rod|del_oso|del_rod
function evid2_delete($id_osoba,$id_rodina,$cmd='confirm') { trace();
  global $USER;
  user_test();
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
//    $x= select1('SUM(castka)','platba',"id_osoba=$id_osoba");
//    if ( $x) $duvod[]= "zaplatil$a $x Kč";
    $xr= pdo_qry("SELECT COUNT(*) AS _x_ FROM spolu JOIN pobyt USING (id_pobyt)
                    JOIN akce ON id_akce=id_duakce WHERE id_osoba=$id_osoba AND spec=0 AND zruseno=0");
    list($x)= pdo_fetch_array($xr);
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
    $rs= query("UPDATE osoba JOIN tvori USING (id_osoba) SET deleted='D' WHERE id_rodina=$id_rodina");
    $no= pdo_affected_rows($rs);
    $rs= query("UPDATE rodina SET deleted='D' WHERE id_rodina=$id_rodina");
    $nr= pdo_affected_rows($rs);
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
# --------------------------------------------------------------------------------------- evid2 cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid2_cleni($id_osoba,$id_rodina,$filtr) { //trace();
  global $USER;
  $access= $USER->access;
  $msg= '';
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
  // pouze při použití filtru na služby během pobytu přidej tabulky spolu, pobyt
  $join_pobyt= strpos($filtr,"AND funkce=")!==false 
      ? "LEFT JOIN spolu AS os ON os.id_osoba=o.id_osoba
        LEFT JOIN pobyt AS op USING (id_pobyt)"
      : "";
//   $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $css= array('','ezer_ys','ezer_fa','ezer_db');
    $qc= pdo_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access AS o_access,
        rt.id_tvori,rt.role,o.deleted,of.id_rodina,nazev,of.access AS r_access
      FROM osoba AS o
        JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
        LEFT JOIN dar AS od ON od.id_osoba=o.id_osoba AND od.deleted=''
        JOIN rodina AS of ON of.id_rodina=ot.id_rodina -- AND of.access & $access
        JOIN tvori AS rt ON rt.id_rodina=of.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
        $join_pobyt 
      WHERE o.id_osoba=$id_osoba AND $filtr -- AND rto.access & $access
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= pdo_fetch_object($qc)) ) {
      $ido= $c->id_osoba;
      $idr= $c->id_rodina;
      $clen[$idr][$ido]= $c;
      $style= ($c->o_access & $access ? '' : '_');
      $clen[0][$ido].= ",{$c->nazev}:$idr:{$css[$c->r_access]}";
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
        $barva= $c->deleted ? 0 : ($c->o_access & $access ? 1 : 2);  // smazaný resp. nedostupný
        $cleni.= "|$ido|$c->id_tvori|$barva|$rodiny|$c->o_access|$c->prijmeni $c->jmeno|$c->_vek|$role";
      }
    }
  }
  else { // ------------------------------------ rodiny
    $qc= pdo_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access,
        rt.id_tvori,rt.role,r.id_rodina,r.nazev,r.access AS r_access,
        GROUP_CONCAT(CONCAT(otr.nazev,/*'-',otr.access,*/':',otr.id_rodina,
          IF(otr.access=1,':ezer_ys',IF(otr.access=2,':ezer_fa',IF(otr.access=3,':ezer_db','')))))
          AS _rodiny,rto.deleted
      FROM rodina AS r
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
        JOIN tvori AS ot ON ot.id_osoba=rto.id_osoba
        JOIN rodina AS otr ON otr.id_rodina=ot.id_rodina -- AND otr.access & $access
      WHERE r.id_rodina=$id_rodina AND $filtr 
        -- AND (rto.access & $access OR rto.access=0) // MSM povolil 240628
      GROUP BY id_osoba
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= pdo_fetch_object($qc)) ) {
      if ( !isset($rodiny[$c->id_rodina]) ) {
        $rodiny[$c->id_rodina]= "{$c->nazev}:{$c->id_rodina}";
        if ( !$rodina ) $rodina= $c->id_rodina;
      }
      if ( $c->id_rodina!=$rodina ) continue;
      $vek= $c->narozeni=='0000-00-00' ? '?' : roku_k($c->narozeni);
      $barva= $c->deleted=='';  // nesmazaný
      $cleni.= "|$c->id_osoba|$c->id_tvori|$barva|$c->_rodiny|$c->access|$c->prijmeni $c->jmeno|$vek|$c->role";
//                                                         display("{$c->jmeno} {$c->narozeni} $vek");
    }
    $msg= $cleni ? '' : "rodina neobsahuje žádné členy";
  }
  $ret= (object)array('cleni'=>$cleni ? substr($cleni,1) : '','rodina'=>$rodina,'msg'=>$msg);
//                                                         debug($ret);
  return $ret;
}
# ----------------------------------------------------------------------------- evid2 browse_act_ask
# obsluha browse s optimize:ask pro seznam akcí dané osoby
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
function evid2_browse_act_ask($x) {
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
    $qp= pdo_qry("
      SELECT a.id_duakce as ida,p.id_pobyt as idp,s.id_spolu as ids,p.funkce as fce,
        YEAR(a.datum_od) as rok,a.nazev as akce,p.funkce as _fce,narozeni,datum_od,a.access AS org
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING(id_pobyt)
      JOIN osoba AS o USING(id_osoba)
      WHERE $x->cond
      ORDER BY $order a.id_duakce
      -- LIMIT 0,50
    ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
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
# ---------------------------------------------------------------------------- evid2 pridej_k_rodine
# ASK přidání do dané rodiny, pokud ještě osoba v rodině není
# spolupracuje s: akce2_auto_jmena1,akce2_auto_jmena1L
# info = {id,nazev,role}
function evid2_pridej_k_rodine($id_rodina,$info,$cnd='') { trace();
  $ret= (object)array('tvori'=>0,'msg'=>'');
  $ido= $info->id;
  $je= select("COUNT(*)","tvori","id_rodina=$id_rodina AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg= "$info->nazev už v rodině je";
  }
  else {
    if ( query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUE ($id_rodina,$ido,'p')") ) {
      $ret->tvori= pdo_insert_id();
      $ret->ok= 1;
    }
    else  $ret->msg= 'chyba při vkládání';
  }
  return $ret;
}
# -------------------------------------------------------------------------- evid2 spoj_osoba_rodina
# ASK přidání do dané rodiny, pokud ještě osoba v rodině není
# pokud idr, ido nejsou jediné klíče, vrátí ok=0
# pro spoj = 0 vrátí otázku
function evid2_spoj_osoba_rodina($ido1,$ido2,$idr1,$idr2,$spoj) { trace();
  $ret= (object)array('ok'=>0,'msg'=>'');
  $ido= $ido1 ?: $ido2;
  $idr= $idr1 ?: $idr2;
                                                display("ido=$ido,idr=$idr");
  if ( !is_numeric($idr) || !is_numeric($ido) ) goto end;
  $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
  if ( $je ) goto end;
  // jinak je spoj, nebo se poptej
  $ret->ok= 1;
  if ( $spoj ) {
    query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUE ($idr,$ido,'d')");
  }
  else {
    $o= select1("concat(prijmeni,' ',jmeno,'/',id_osoba)",'osoba',"id_osoba=$ido");
    $r= select1("concat(nazev,'/',id_rodina)",'rodina',"id_rodina=$idr");
    $ret->msg= "Opravdu mám spojit rodinu $r a osobu $o? (role bude nastavena jako 'd' - nutno upravit)";
  }
end:
  return $ret;
}
# ==========================================================================================> . YMCA
# ---------------------------------------------------------------------------==> .. evid2 ymca darci
# správa dárců pro YS
function evid2_ymca_darci($org,$kdy='loni') {
  $html= '';
  $min= 4999;
  $rok= $kdy=='loni' ? date('Y')-1 : date('Y');
  $tits= explode(',','jméno:20,dar:10,činný č.:8,telefon:12,mail:40,ID');
              debug($tits,'1');
  $flds= explode(',','jmeno,dar,cc,telefon,mail,id_osoba');
  $clmn= array();
  $rd= pdo_qry("SELECT id_osoba,CONCAT(jmeno,' ',prijmeni),kontakt,email,telefon,
      SUM(IF(ukon='d',castka,0)) AS _dar,SUM(IF(ukon='p',1,0))
    FROM dar JOIN osoba USING (id_osoba) 
    WHERE ukon IN ('d','p') AND YEAR(dat_od)=$rok AND dar.access=$org
    GROUP BY id_osoba HAVING _dar>$min
    ORDER BY _dar DESC
  ");
  $html.= "<table>";
  while ($rd && list($ido,$jmeno,$k,$email,$telefon,$dar,$cc)= pdo_fetch_array($rd)) {
    $clmn[]= array('jmeno'=>$jmeno,'dar'=>$dar,'cc'=>$cc==1?'ano':'ne',
        'telefon'=>$k==1?$telefon:'','mail'=>$k==1?$email:'','id_osoba'=>$ido);
  }
  // tisk přes sta2_excel_export
  $ret= sta2_table($tits,$flds,$clmn);
  $tab= (object)array('tits'=>$tits,'flds'=>$flds,'clmn'=>$clmn);
  $rete= sta2_excel_export("Štědří dárci roku $rok",$tab);
  return "$rete->html<br><br>$ret->html";
}
# --------------------------------------------------------------------------==> .. evid2 ymca sprava
# správa členů pro YS
function evid2_ymca_sprava($org,$par,$title,$export=false,$akce=0) {
  $ret= (object)array('error'=>0,'html'=>'');
  switch ($par->op) {
  case 'hlaseni':
  case 'letos':
  case 'loni':
    $ret= evid2_ymca_sestava($org,$par,$title,$export);
//                                                         debug($ret,"evid2_ymca_sestava");
    break;
  case 'Valna-schuze':
    $ret= evid2_ymca_sestava($org,$par,$title,0,$akce);
    $ret1= evid2_ymca_sestava($org,$par,$title,1,$akce);
    $ret2= sta2_excel_export($title,$ret1);
    $ret->html= "{$ret2->html}<br>{$ret->html}";
    break;
  case 'zmeny':
    $ret= evid2_recyklace_pecounu($org,$par,$title,0);
    break;
  }
  // případně export do Excelu
  return $export ? sta2_excel_export($title,$ret) : $ret;
//   return $ret;
}
# ------------------------------------------------------------------------------- evid2 ymca_sestava
# generování přehledu členstva
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# _clen_od,_cinny_od,_prisp,_dary
function evid2_ymca_sestava($org,$par,$title,$export=false,$akce=0) {
  $ret= (object)array('html'=>'','err'=>'');
  $rok= date('Y') - (isset($par->rok) ? $par->rok : 0);
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  $jen_cinni= $par->jen_cinni ? 1 : 0;
  $jen_akce= $akce ? "id_akce=$akce" : '1';
  // test korektnosti organizace
  $organizace= $org==1 ? 'YMCA Setkání' : ( $org==2 ? "YMCA Familia" : '');
  if ( !$organizace ) {
    $ret->err= "Musí být zvolena organizace (barva aplikace)";
    goto end;
  }
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $clenu= $cinnych= $cinnych_cr= $prispevku= $daru= $dobrovolniku= $novych= $novych_cr= 0;
  $msg= $akce ? '' : "<br><br>Ve výběru jsou účastníci akcí roku $rok";
  $qry= "SELECT
           MAX(IF(YEAR(datum_od)=$rok AND p.funkce=1,1,0)) AS _vps,
           MAX(IF(YEAR(datum_od)=$rok AND p.funkce=99,1,0)) AS _pec,
           os.id_osoba,os.prijmeni,os.jmeno,os.narozeni,os.sex,
           IF(os.obec='',r.obec,os.obec) AS obec,
           IF(os.ulice='',r.ulice,os.ulice) AS ulice,
           IF(os.psc='',r.psc,os.psc) AS psc,
           IF(os.email='',r.emaily,os.email) AS email,
           GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
           GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka) ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
         FROM osoba AS os
         LEFT JOIN tvori AS ot ON os.id_osoba=ot.id_osoba
         LEFT JOIN rodina AS r USING(id_rodina)
         LEFT JOIN dar AS od ON os.id_osoba=od.id_osoba AND od.deleted=''
         LEFT JOIN spolu AS s ON s.id_osoba=os.id_osoba
         LEFT JOIN pobyt AS p USING (id_pobyt)
         LEFT JOIN akce AS a ON a.id_duakce=p.id_akce
         WHERE os.deleted='' AND {$par->cnd} AND (dat_do='0000-00-00' OR YEAR(dat_do)>=$rok)
           AND os.access&$org AND od.access&$org AND a.access&$org AND $jen_akce
        -- AND os.id_osoba=91 -- Jan Baletka
         GROUP BY os.id_osoba HAVING {$par->hav}
         ORDER BY os.prijmeni";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $id_osoba= $x->id_osoba;
    $honorabilis= 0;
    // rozbor úkonů
    $_clen_od= $_cinny_od= $_cinny_cr_od= $_prisp= $prisp_letos= $_dary= 0;
    foreach(explode('|',$x->_ukony) as $uddc) {
      list($u,$d1,$d2,$c)= explode(':',$uddc);
      switch ($u) {
      case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
      case 'd': if ( $d1==$rok ) $_dary+= $c; break;
      case 'H': $honorabilis++; 
      case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
      case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
      case 'Y': if ( $d2<=$rok && (!$_cinny_cr_od && $d1<=$rok || $d1<$_cinny_cr_od) ) $_cinny_cr_od= $d1; break;
      }
    }
//                                 display("$x->prijmeni $x->jmeno: clen_od=$_clen_od, cinny_od=$_cinny_od, cinny_cr_od=$_cinny_cr_od, prisp=$_prisp, dary=$_dary");
    $prispevku+= $_prisp;
    $daru+= $_dary;
    if ( !($_clen_od || $_cinny_od || $_cinny_cr_od)) continue;
    if ($jen_cinni && !($_cinny_od || $_cinny_cr_od)) continue;
    $clenu+= $_clen_od ? 1 : 0;
    $cinnych+= $_cinny_od ? 1 : 0;
    $cinnych_cr+= $_cinny_cr_od ? 1 : 0;
    $dobrovolniku+= $x->_vps && $_cinny_od>0 || $_cinny_cr_od>0 || $x->_pec ? 1 : 0;
    $novych+= $_cinny_od==$rok ? 1 : 0;
    $novych_cr+= $_cinny_cr_od==$rok ? 1 : 0;
    // pokračujeme jen s členy
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
//  '1,prijmeni,jmeno,ulice,obec,psc,_naroz,_b_c,_prisp,_YS,_email,_cinny_letos,_dobro'}}
      switch ( $f ) {
      // export
      case '1':         $clmn[$n][$f]= 1; break;
      case '_naroz_ymd':$clmn[$n][$f]= substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2); break;
      case '_b_c':      $clmn[$n][$f]= $honorabilis ? 'H' : ($_cinny_cr_od>0 ? 'Y' : ($_cinny_od>0 ? 'č' : 'b')); break;
      case '_YS':       $clmn[$n][$f]= 'YMCA Setkání'; break;
      case '_cinny_letos':$clmn[$n][$f]= $_cinny_od==$rok ? 1 : ''; break;
      case '_cinny_cr_letos':$clmn[$n][$f]= $_cinny_cr_od==$rok ? 1 : ''; break;
      case '_dobro':    $clmn[$n][$f]= $x->_vps && $_cinny_od>0  || $x->_pec ? 1 : ''; break;
      // přehled
      case '_clen_od':  $clmn[$n][$f]= $_clen_od; break;
      case '_cinny_od': $clmn[$n][$f]= $_cinny_od; break;
      case '_cinny_cr_od': $clmn[$n][$f]= $_cinny_cr_od; break;
      case '_prisp':    $clmn[$n][$f]= $_prisp; break;
      case '_dary':     $clmn[$n][$f]= $_dary; break;
      case '_naroz':    $clmn[$n][$f]= sql_date1($x->narozeni); break;
        $clmn[$n][$f]= $del;
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
//                    debug($clmn,"$x->prijmeni $x->jmeno");
  }
  // přidání sumarizace
  if (!$akce) {
    $n++;
    $clmn[$n]['obec']= '.SUMA:.';
    $clmn[$n]['_clen_od']= $clenu;
    $clmn[$n]['_cinny_od']= $cinnych;
    $clmn[$n]['_cinny_cr_od']= $cinnych_cr;
    $clmn[$n]['_prisp']= $prispevku;
    $clmn[$n]['_dary']= $daru;
    $clmn[$n]['_dobro']= $dobrovolniku;
    $clmn[$n]['_cinny_letos']= $novych;
    $clmn[$n]['_cinny_cr_letos']= $novych_cr;
//                      debug($clmn,"SUMA");
  }
//   $ret= sta2_table_graph($par,$tits,$flds,$clmn,$export);
  $ret= sta2_table($tits,$flds,$clmn,$export);
end:
  $ret->html.= $msg;
  return $ret;
}
# --------------------------------------------------------------------==> .. evid2_recyklace_pecounu
# generování přehledu pečounů pro recyklaci
# - předpokládá se spuštění ve stejném roce jako byl letní kurz pokud par.rok=0
# - nebo v loňském roce, pokud je rok=1
function evid2_recyklace_pecounu($org,$par,$title,$provest) {
  $ret= (object)array('html'=>'','err'=>'');
  // test korektnosti organizace
  $organizace= $org==1 ? 'YMCA Setkání' : ( $org==2 ? "YMCA Familia" : '');
  if ( !$organizace ) {
    $ret->err= "Musí být zvolena organizace (barva aplikace)";
    goto end;
  }
  $letos= date('Y') - $par->rok;
  $rok_od= $letos;
  $rok_do= $rok_od-1;
  $den_do= "$rok_do-12-31";
  $den_od= "$rok_od-01-01";
  $html= '';
  $pryc= $nechat= $novi= array();
  // průzkum pečounů
  $pr= pdo_qry("
    SELECT id_osoba,prijmeni,jmeno,ukon,pfunkce,
      MIN(YEAR(dat_od)) AS _od,
      MAX(YEAR(dat_do)) AS _do,
      MIN(YEAR(a.datum_od)) AS _poprve,
      MAX(YEAR(a.datum_od)) AS _naposled,
      GROUP_CONCAT(DISTINCT ukon SEPARATOR '') AS _ukony,
      SUM(IF(YEAR(a.datum_od)=$rok_do,1,0)) AS _loni,
      SUM(IF(YEAR(a.datum_od)=$rok_od,1,0)) AS _letos,
      MAX(pfunkce) AS _pfunkce,
      narozeni
    FROM osoba AS o
    LEFT JOIN dar AS d USING (id_osoba)
    JOIN spolu AS s USING (id_osoba)
    JOIN pobyt AS p USING (id_pobyt)
    JOIN akce AS a ON id_akce=id_duakce
    WHERE IFNULL(d.deleted,'')='' AND funkce=99
      AND o.access&$org AND IFNULL(d.access,3)&$org AND a.access&$org 
    GROUP BY id_osoba
    ORDER BY prijmeni");
  while ( $pr && ($p= pdo_fetch_object($pr)) ) {
    $pec= (object)array('id'=>$p->id_osoba,'jmeno'=>"{$p->prijmeni} {$p->jmeno}");
    // přeskočit činné členy a team
    if ( strpos($p->_ukony,'c')!==false ) continue;
    if ( $p->_pfunkce==7 ) continue;
    if ( $p->_poprve==$rok_od && strpos($p->_ukony,'b')===false
    || ( $p->_naposled==$rok_od && strpos($p->_ukony,'b')===false )
    ) {
      $novi[]= $pec;
    }
    else if ( $p->_naposled<$rok_od && strpos($p->_ukony,'b')!==false && $p->_do==0 ) {
      $pryc[]= $pec;
    }
    else if ( $p->_naposled==$rok_od ) {
      $nechat[]= $pec;
    }
  }
//                                                 debug($novi,"noví");
//                                                 debug($pryc,"pryč");
//                                                 debug($nechat,"nechat");
  // noví pečouni
  $html.= "<h3>Úprava členství pečounů v $organizace</h2>";
  $html.= "<h3>Noví členové tj. letošní pečouni, kteří nejsou členy</h3>";
  $del= '';
  foreach ($novi as $nov) {
    $html.= "$del{$nov->jmeno}"; $del= ', ';
    if ( $provest ) {
      query("INSERT INTO dar (access,id_osoba,ukon,dat_od,note)
             VALUES ($org,$nov->id,'b','$den_od','pečoun $rok_od')");
    }
  }
  // staří pečouni
  $html.= "<h3>Ponechaní členové tj. dříve i letos aktivní pečouni, kteří jsou již členy</h3>";
  $del= '';
  foreach ($nechat as $nec) {
    $html.= "$del{$nec->jmeno}"; $del= ', ';
  }
  // nečinní pečouni
  $html.= "<h3>Členové, kteří budou vyřazeni tj. letos už neaktivní pečouni, kteří jsou dosud členy</h3>";
  $del= '';
  foreach ($pryc as $pry) {
    $html.= "$del{$pry->jmeno}"; $del= ', ';
    if ( $provest ) {
      query("UPDATE dar SET dat_do='$den_do'
             WHERE access=$org AND id_osoba={$pry->id} AND ukon='b' AND dat_do='0000-00-00'");
    }
  }
  // návrat
  $ret->html= $html;
end:
  return $ret;
}
# =======================================================================================> . MAILIST
# -----------------------------------------------------------------------==> .. evid2_browse_mailist
# BROWSE ASK
# obsluha browse s optimize:ask
# x->cond = id_mailist
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
# x->selected= null | seznam key_id, které mají být předány - použití v kombinaci se selected(use)
function evid2_browse_mailist($x) {
  global $test_clmn,$test_asc, $y;
//                                                        debug($x,"evid2_browse_mailist");
//                                                         return;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  // předání selected
  if ( $x->selected ) {
    $selected= explode(',',$x->selected);
  }
  switch ($x->cmd) {
  case 'browse_export':
  case 'browse_load':
    $zz= array();
    # získej pozice PSČ
    $lat= $lng= array();
    $qs= "SELECT psc,lat,lng FROM psc_axy GROUP BY psc";
    $rs= pdo_qry($qs);
    while ( $rs && ($s= pdo_fetch_object($rs)) ) {
      $psc= $s->psc;
      $lat[$psc]= $s->lat;
      $lng[$psc]= $s->lng;
    }
    # pokus se získat podmínku - zjednodušeno na show=[*,vzor]
    $whr= $hav= array();
    if ( $x->show ) foreach ( $x->show as $fld => $show) {
      $i= 0; $typ= $show->$i;
      $i= 1; $vzor= $show->$i;
      $beg= '^';
      if ($typ!='*') break;
      $end= substr($vzor,-1)=='$' ?'$' : '.*';
      $not= substr($vzor,0,1)=='-';
      if ( $not ) $vzor= substr($vzor,1);
      $vzor= strtr($vzor,array('?'=>'.','*'=>'.*','$'=>''));
      if (in_array($fld,array('psc','obec'))) {
        $hav[]= "_$fld REGEXP '$beg$vzor$end' ";
      }
      elseif (in_array($fld,array('prijmeni','jmeno'))) {
        $whr[]= "o.$fld REGEXP '$beg$vzor$end' ";
      }
    }
//                                                                debug($whr,"WHERE");
//                                                                debug($hav,"HAVING");
    # získej sexpr z mailistu id=c.cond
    list($nazev,$qo,$komu)= select('ucel,sexpr,komu','mailist',"id_mailist={$x->cond}");
    // přidej případnou podmínku 
    if ( count($whr) ) {
      $cond= implode(' AND ',$whr);
      $qo= str_replace("WHERE","WHERE $cond AND ",$qo);
    }
    if ( count($hav) ) {
      $cond= implode(' AND ',$hav);
      $qo= str_replace("ORDER BY","HAVING $cond ORDER BY",$qo);
    }
//                                                                display($qo);
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $id= $komu=='o' ? $o->_id : $o->_idr;
      if ( $x->selected && !in_array($id,$selected) ) continue;
      if ( $komu=='o' ) {
        list($prijmeni,$jmeno)= explode(' ',$o->_name);
      }
      else {
        $jm= strpos($o->_name,' ');
        $prijmeni= substr($o->_name,0,$jm);
        $jmeno= substr($o->_name,$jm+1);
      }
      $psc= $o->_psc;
      $_lat= isset($lat[$psc]) ? $lat[$psc] : 0;
      $_lng= isset($lng[$psc]) ? $lng[$psc] : 0;
      if ($komu=='o') {
        list($lt,$lg)= select('lat,lng','osoba_geo',"id_osoba=$id");
        if ($lt) { $_lat= $lt; $_lng= $lg; }
      }
      $zz[]= (object)array(
      'id_o'=>$id,
      'lat'=> $_lat,
      'lng'=> $_lng,
      'access'=>$o->access,
      'prijmeni'=>$prijmeni,
      'jmeno'=>$jmeno,
      'ulice'=>$o->_ulice,
      'psc'=>$psc,
      'obec'=>$o->_obec,
      '_vek'=> $komu=='o' ? $o->_vek : $o->_spolu,
      'mail'=>$o->_email,
      'telefon'=>$o->_telefon,
      '_id_o'=>$id
      );
    }
    # ==> ... případný výběr - zjednodušeno na show=[*,vzor]
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
    # ==> ... řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('_id_o'));
      if ( $numeric ) {
//                                         display("usort $test_clmn $test_asc/numeric");
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
            $a0= mb_substr($a->$test_clmn,0,1);
            $b0= mb_substr($b->$test_clmn,0,1);
            if ( $a0=='(' ) {
              $c= -$test_asc;
            }
            elseif ( $b0=='(' ) {
              $c= $test_asc;
            }
            else {
              $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
            }
            return $c;
          });
        }
      }
    }
    if ( $x->cmd=='browse_load' ) {
      # předání pro browse
      $y->values= $zz;
      $y->from= 0;
      $y->cursor= 0;
      $y->rows= count($zz);
      $y->count= count($zz);
      $y->ok= 1;
      array_unshift($y->values,null);
    }
    else if ( $x->cmd=='browse_export' ) { #==> ... browse_export
      // transformace dat
      $clmn= array();
      foreach($zz as $z) {
        $row= array(
          'jmeno'  => "{$z->jmeno} {$z->prijmeni}",
          'ulice'  => $z->ulice,
          'psc'    => $z->psc,
          'obec'   => $z->obec,
          'telefon'=> $z->telefon,
          'mail'   => $z->mail
        );
        $clmn[]= $row;
      }
      // tisk přes sta2_excel_export
      $tab= (object)array(
        'tits'=>explode(',',"jmeno:30,ulice:20,psc:6,obec:20,telefon:25,mail:30"),
        'flds'=>explode(',',"jmeno,ulice,psc,obec,telefon,mail"),
        'clmn'=>$clmn
      );
      $ret= sta2_excel_export("Vybrané kontakty ze seznamu '$nazev'",$tab);
      $y->par= $x->par;
      $y->par->html= $ret->html;
    }
    break;
  default:
    fce_error("metoda {$x->cmd} není podporována");
  }
//                                                              debug($y);
  return $y;
}
# ==========================================================================================> . MAPA
# ---------------------------------------------------------------------------==> .. mapa2 geo_export
# vrátí strukturu pro gmap
function mapa2_geo_export($par) {
  // pro doplnění o LOC_CITY_DISTR_CODE|LOC_GEO_LATITUDE|LOC_GEO_LONGITUDE
  $tab= $par->tab;
  $html= '';
  $n= 0;
  $fpath= "docs/geo-$tab.csv";
  $flds= "id;ulice;psc;obec;code;lat;lng";
  $f= @fopen($fpath,'w');
  fputs($f, chr(0xEF).chr(0xBB).chr(0xBF));  // BOM pro Excel
  fputcsv($f,explode(';',$flds),';');
  switch ($tab) {
  case 'osoba': 
    $mr= pdo_qry("
      SELECT id_osoba,ulice,psc,obec
      FROM osoba 
      WHERE deleted='' AND adresa=1 AND stat IN ('','CZ') AND (ulice!='' OR obec!='')
      ORDER BY id_osoba
    ");
    while ( $mr && list($id,$ulice,$psc,$obec)= pdo_fetch_row($mr) ) {
      fputcsv($f,array($id,$ulice,$psc,$obec,0,0,0),';');
      $n++;
    }
    break;
  case 'rodina': 
    $mr= pdo_qry("
      SELECT id_rodina,ulice,psc,obec
      FROM rodina
      WHERE deleted='' AND stat IN ('','CZ') AND (ulice!='' OR obec!='')
      ORDER BY id_rodina
    ");
    while ( $mr && list($id,$ulice,$psc,$obec)= pdo_fetch_row($mr) ) {
      fputcsv($f,array($id,$ulice,$psc,$obec,0,0,0),';');
      $n++;
    }
    break;
  }
  fclose($f);
  $html.= "tabulka $tab má $n relevantních záznamů pro geolokaci - jsou zde ke "
      . "<a href='$fpath'>stáhnutí</a>";
  return $html;
}
# ------------------------------------------------------------------------------------ mapa2 skupiny
# přečtení seznamu skupin
function mapa2_skupiny() {  trace();
//  global $json;
  $goo= "https://docs.google.com/spreadsheets/d";
  $key= "1mp-xXrF1I0PAAXexDH5FA-n5L71r5y0Qsg75cU82X-4";         // Seznam skupin - kontakty
  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
  $sheet= "List 1";
  $x= file_get_contents("$goo/$key/gviz/tq?tqx=out:json"); //&sheet=$sheet");
//                                         display($x);
  $xi= strpos($x,$prefix);
  $xl= strlen($prefix);
//                                         display("xi=$xi,$xl");
  $x= substr(substr($x,$xi+$xl),0,-2);
//                                         display($x);
  $tab= json_decode($x)->table;
//                                         debug($tab,$sheet);
  // projdeme získaná data
  $psc= $note= $clmns= array();
  $n= 0;
  $msg= '';
  if ( $tab ) {
    foreach ($tab->rows as $crow) {
      $row= $crow->c;
      if ( $row[0]->v=="ZVLÁŠTNÍ SKUPINY:" ) break;     // konec seznamu
      $skupina= $row[0]->v;
      $p= $row[1]->v;
      $p= strtr($p,array(' '=>'','?'=>'',"\n"=>''));
      $aktual= $row[2]->v;
      if ( preg_match("/(\d+),(\d+),(\d+)/",$x,$m) )
        $aktual= "$m[3].$m[2].$m[1]";
      $kontakt= $row[3]->v;
      $email= $row[4]->v;
      $pozn= $row[5]->v;
      if ( strlen($p)==5 ) {
        $psc[$p]= $pozn;
        $note[$p]= $skupina;
        $n++;
        // podrobnosti do pole $clmns
        $clmns[$p]=
          "<h3>$skupina</h3><p>Kontakt:$kontakt, <b>$email</b></p>"
        . "<p>$pozn</p><p style='text-align:right'><i>aktualizováno: $aktual</i></p>";
      }
      else {
//                                         debug($crow,"problém");
        $msg.= " $p";
      }
    }
  }
  // konec
end:
  $ret= mapa2_psc($psc,$note,1);
  $msg= $msg ? "<br><br>Problém nastal pro PSČ: $msg" : '';
  $msg.= $ret->err ? "<br><br>$ret->err" : '';
  $ret->err= '';
  $ret->clmns= $clmns;
  $ret->msg= "Je zobrazeno $n skupin z tabulky <b>Seznam skupin - kontakty</b>$msg";
  return $ret;
}
# -----------------------------------------------------------------------------==> .. mapa2 psc_list
# vrátí strukturu pro gmap
function mapa2_psc_list($psc_lst) {
  $psc= $obec= array();
  foreach (explode(',',$psc_lst) as $p) {
    $psc[$p]= $p;
  }
  return mapa2_psc($psc,$obec);
}
# ----------------------------------------------------------------------------------==> .. mapa2 psc
# vrátí strukturu pro gmap
# icon = CIRCLE[,scale:1-10][,ontop:1]|cesta k bitmapě nebo pole psc->icon
function mapa2_psc($psc,$obec,$psc_as_id=0,$icon='') {
//                                                debug($psc,"mapa2_psc");
  // k PSČ zjistíme LAN,LNG
  $ret= (object)array('mark'=>'','n'=>0);
  $ic= '';
  if ( $icon ) {
    if ( !is_array($icon) )
      $ic=",$icon";
  }
  $marks= $err= '';
  $mis_psc= array();
  $err_psc= array();
  $chybi= array();
  $n= 0; $del= '';
  foreach ($psc as $p=>$tit) {
    $p= trim($p);
    if ( preg_match('/\d\d\d\d\d/',$p) ) {
      $qs= "SELECT psc,lat,lng FROM psc_axy WHERE psc='$p'";
      $rs= pdo_qry($qs);
      if ( $rs && ($s= pdo_fetch_object($rs)) ) {
        $n++;
        $o= isset($obec[$p]) ? $obec[$p] : $p;
        $title= str_replace(',','',"$o:$tit");
        $id= $psc_as_id ? $p : $n;
        if ( is_array($icon) )
          $ic= ",{$icon[$p]}";
        $marks.= "{$del}$id,{$s->lat},{$s->lng},$title$ic"; $del= ';';
      }
      else {
        $err_psc[$p].= " $p";
        if ( !in_array($p,$chybi) ) 
          $chybi[]= $p;
      }
    }
    else {
      $mis_psc[$p].= " $p";
    }
  }
  // zjištění chyb
  if ( count($err_psc) || count($mis_psc) ) {
    if ( ($ne= count($mis_psc)) ) {
      $err= "$ne PSČ chybí nebo má špatný formát. Týká se to: ".implode(' a ',$mis_psc);
    }
    if ( ($ne= count($err_psc)) ) {
      $err.= "<br>$ne PSČ se nepovedlo lokalizovat. Týká se to: ".implode(' a ',$err_psc);
    }
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n,'err'=>$err,'chybi'=>$chybi);
//                                                    debug($chybi,"chybějící PSČ");
  return $ret;
}
# ------------------------------------------------------------------------------==> .. mapa2 psc_set
# ASK
function mapa2_psc_set($psc,$latlng) {  trace();
  list($lat,$lng)= preg_split("/,\s*/",$latlng);
  if ( !$psc || !$lat || !$lng ) goto end;
  $ex= select("COUNT(*)",'psc_axy',"psc='$psc'");
  if ($ex) {
    query("UPDATE psc_axy SET lat='$lat',lng='$lng' WHERE psc='$psc'");
  }
  else {
    query("INSERT INTO psc_axy (psc,lat,lng) VALUE ('$psc','$lat','$lng')");
  }
end:  
  return 1;
}
# ------------------------------------------------------------------------------==> .. mapa2 ctverec
# ASK
# obsah čtverce $clen +- $dist (km) na všechny strany
# vrací objekt {
#   err:  0/1
#   msg:  text chyby
#   rect: omezující obdélník jako SW;NE
#   poly: omezující polygon zmenšený o $perc % oproti obdélníku
function mapa2_ctverec($mode,$id,$dist,$perc) {  trace();
  $ret= (object)array('err'=>0,'msg'=>'');
  // zjištění polohy člena
  $lat0= $lng0= 0;
  $qc= in_array($mode,array('o','h','m'))
   ? "SELECT IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba=$id"
   : "SELECT lat,lng
      FROM rodina AS r
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina=$id";
  $rc= pdo_qry($qc);
  if ( $rc && $c= pdo_fetch_object($rc) ) {
    $lat0= $c->lat;
    $lng0= $c->lng;
  }
  if ( !$lat0 ) { $ret->msg= "nelze najít polohu $mode/$id"; $ret->err++; goto end; }
  // čtverec  SW;NE
  $delta_lat= 0.0089913097;
  $delta_lng= 0.0137464041;
  $N= $lat0-$dist*$delta_lat;
  $S= $lat0+$dist*$delta_lat;
  $W= $lng0-$dist*$delta_lng;
  $E= $lng0+$dist*$delta_lng;
  $ret->rect= "$N,$W;$S,$E";
  // polygon
  $d_lat= abs($N-$S)*$perc/300;
  $d_lng= abs($W-$E)*$perc/100;
  $N= $N-$d_lat;
  $S= $S+$d_lat;
  $W= $W+$d_lng;
  $E= $E-$d_lng;
  $ret->poly= "$N,$W;$N,$E;$S,$E;$S,$W";
end:
//                                                 debug($ret,"geo_get_ctverec");
  return $ret;
}
# --------------------------------------------------------------------------------- mapa2 ve_ctverci
# ASK
# vrátí jako seznam id_$tab bydlících v oblasti dané obdélníkem 'x,y;x,y'
# podmnožinu předaných ids a k tomu řetezec definující značky v mapě
# pokud je rect prázdný - vrátí vše, co lze lokalizovat
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_ve_ctverci($mode,$rect,$ids,$max=5000) { trace();
  global $ezer_version;
  $ret= (object)array('err'=>'','rect'=>$rect,'ids'=>'','marks'=>'','pocet'=>0);
  if ( $rect ) {
    list($sell,$nwll)= explode(';',$rect);
    $se= explode(',',$sell);
    $nw= explode(',',$nwll);
    $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $nw[0] AND $se[0]
      AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $se[1] AND $nw[1]";
  }
  else {
    $poloha= "lat!=0 AND lng!=0";
  }
  $qo= in_array($mode,array('o','h','m'))
   ? "SELECT id_osoba,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba IN ($ids) AND $poloha "
   : // r|f
     "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM rodina AS r
      LEFT JOIN rodina_geo AS g USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina IN ($ids) AND $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= ($rect ? "Ve výřezu mapy je" : "Je požadováno"). " příliš mnoho bodů "
        . "({$ret->pocet} nejvíc lze $max)";
    }
    else {
      $del= $semi= '';
      while ( $ro && list($id,$geo,$lat,$lng)= pdo_fetch_row($ro) ) {
        $ret->ids.= "$del$id"; 
        $color= $geo ? 'green' : 'gold';
        $ret->marks.= "$semi$id,$lat,$lng,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
        $del= ','; $semi= ';';
      }
    }
  }
end:  
  return $ret;
}
# ----------------------------------------------------------------------------- mapa2 psc_v_polygonu
# ASK
# vrátí jako seznam PSČ ležící v oblasti dané polygonem 'x,y;...'
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_psc_v_polygonu($poly) { trace();
  $ret= (object)array('err'=>'','pscs'=>'','pocet'=>0);
  // nalezneme ohraničující obdélník a převedeme polygon do interního tvaru
  $lat_min= $lng_min= 999;
  $lng_max= $lng_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lng)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lng_min= min($lng_min,$lng);
    $lng_max= max($lng_max,$lng);
    $x[]= floatval($lat); 
    $y[]= floatval($lng);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "lat BETWEEN $lat_min AND $lat_max AND lng BETWEEN $lng_min AND $lng_max";
  $qo= "SELECT psc, lat, lng
      FROM psc_axy 
      WHERE $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    $del= '';
    while ( $ro && list($psc,$lat,$lng)= pdo_fetch_row($ro) ) {
      // zjistíme, zda leží uvnitř polygonu
      if ( maps_poly_cross($lat,$lng,$x,$y) ) {
        $ret->pscs.= "$del$psc"; $del= ',';
      }
    }
  }
  return $ret;
}
# --------------------------------------------------------------------------------- mapa2 v_polygonu
# ASK
# vrátí jako seznam id_$tab bydlící v oblasti dané polygonem 'x,y;...'
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_v_polygonu($mode,$poly,$ids,$max=5000) { trace();
  global $ezer_version;
  $ret= (object)array('err'=>'','poly'=>$poly,'ids'=>'','pocet'=>0);
  // nalezneme ohraničující obdélník a přvedeme polygon do interního tvaru
  $lat_min= $lng_min= 999;
  $lng_max= $lng_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lng)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lng_min= min($lng_min,$lng);
    $lng_max= max($lng_max,$lng);
    $x[]= floatval($lat); 
    $y[]= floatval($lng);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $lat_min AND $lat_max 
    AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $lng_min AND $lng_max";
  $qo= in_array($mode,array('o','h','m')) 
   ? "SELECT id_osoba,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba IN ($ids) AND $poloha "
   : "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM rodina AS r
      LEFT JOIN rodina_geo AS g USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina IN ($ids) AND $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= "Je požadováno příliš mnoho bodů {$ret->pocet} nejvíc lze $max";
    }
    else {
      $del= $semi= '';
      while ( $ro && list($id,$geo,$lat,$lng)= pdo_fetch_row($ro) ) {
        // zjistíme, zda leží uvnitř polygonu
        if ( maps_poly_cross($lat,$lng,$x,$y) ) {
          $ret->ids.= "$del$id"; 
          $color= $geo ? 'green' : 'gold';
          $ret->marks.= "$semi$id,$lat,$lng,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
          $del= ','; $semi= ';';
        }
      }
    }
  }
  return $ret;
}
# ---------------------------------------------------------------------------------- maps_poly_cross
# crossing number test for a point in a polygon
# input:   P = a point $x0,$y0,
#        V[] = n-1 vertex points of a polygon with V[n]=V[0], V[i]=$x[i],$y[i]
# returns: 0 = outside, 1 = inside
# This code is patterned after [Franklin, 2000]
# http://softsurfer.com/Archive/algorithm_0103/algorithm_0103.htm
function maps_poly_cross($x0,$y0,$x,$y) {
  $N= count($x)-1;
  $cn= 0;                                               // the crossing number counter
  // loop through all edges of the polygon
  for ($i=0; $i<$N; $i++) {                             // edge from V[i] to V[i+1]
    if ( (($y[$i] <= $y0) && ($y[$i+1] > $y0))          // an upward crossing
      || (($y[$i] > $y0) && ($y[$i+1] <= $y0)) ) {      // a downward crossing
      // compute the actual edge-ray intersect x-coordinate
      $vt= ($y0 - $y[$i]) / ($y[$i+1] - $y[$i]);
//                                                 display("$i:vt=$vt, $x0 ? ".($x[$i] + $vt * ($x[$i+1] - $x[$i])));
      if ( $x0 < ($x[$i] + $vt * ($x[$i+1] - $x[$i])) ) { // P.x < intersect
        $cn++;                                          // a valid crossing of y=P.y right of P.x
      }
    }
  }
//                                                 display("cross($x0,$y0,X,Y)=$cn");
  return $cn & 1;                                         // 0 if even (out), and 1 if odd (in)
}
//# ------------------------------------------------------------------------------- mapa2 mimo_ctverec
//# ASK
//# vrátí jako seznam id_$tab bydlících mimo oblast danou obdélníkem 'x,y;x,y'
//# podmnožinu předaných ids
//# pokud by seznam byl delší než MAX, vrátí chybu
//function mapa2_mimo_ctverec($mode,$rect,$ids,$max=5000) { trace();
//  $ret= (object)array('err'=>'','rect'=>$rect,'ids'=>'','pocet'=>0);
//  list($sell,$nwll)= explode(';',$rect);
//  $se= explode(',',$sell);
//  $nw= explode(',',$nwll);
//  // dotaz na ohraničující obdélník
//  $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $se[0] AND $nw[0] 
//    AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $se[1] AND $nw[1]";
//  $qo= in_array($mode,array('o','h','m')) 
//   ? "SELECT id_osoba, IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
//      FROM osoba AS o
//      LEFT JOIN osoba_geo AS g USING (id_osoba)
//      LEFT JOIN tvori AS t USING (id_osoba)
//      LEFT JOIN rodina AS r USING (id_rodina)
//      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
//      WHERE id_osoba IN ($ids) AND NOT ($poloha) "
//   : "SELECT id_rodina,IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
//      FROM rodina AS r
//      LEFT JOIN rodina_geo AS g USING (id_rodina)
//      LEFT JOIN psc_axy AS a ON a.psc=r.psc
//      WHERE id_rodina IN ($ids) AND NOT ($poloha) ";
//  $ro= pdo_qry($qo);
//  if ( $ro ) {
//    $ret->pocet= pdo_num_rows($ro);
//    if ( $max && $ret->pocet > $max ) {
//      $ret->err= "Ve výřezu mapy je příliš mnoho bodů ({$ret->pocet} nejvíc lze $max)";
//    }
//    else {
//      $del= '';
//      while ( $ro && list($id)= pdo_fetch_row($ro) ) {
//        $ret->ids.= "$del$id"; $del= ',';
//      }
//    }
//  }
//  return $ret;
//}
/** ==========================================================================================> STA2 */
# ====================================================================================> . sta2 mrop
# tabulka struktury účastníků MROP
function sta2_mrop($par,$export=false) {
  $msg= "";
  $msg.= sta2_mrop_vek($par);
  $msg.= sta2_mrop_vliv($par);
  return $msg;
}
# -------------------------------------------------------------------------------==> . sta2 mrop vek
# roční statistika účastníků: průměrný věk, byl předtím na MS
function sta2_mrop_vek($par,$export=false) {
  $msg= "<h3>Kolik jich je a jací jsou</h3><i>Poznámka: starší, zjednodušená verze bez CPR aj.</i><br><br>";
  $AND= '';
//   $AND= "AND iniciace=2002 AND o.id_osoba=5877";
  $celkem= 0;
  $styl= " style='text-align:right'";
  $tab= "<div class='stat'><table class='stat'>
         <tr><th>rok</th><th>účastníci</th><th>bylo na MS</th><th>%</th><th>prům. věk</th></tr>";
  $mr= pdo_qry("
    SELECT iniciace,COUNT(*) AS _kolik,SUM(IF(IFNULL(m._ms,0),1,0)) AS _ms, -- _roky,
      ROUND(AVG(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni))),1) AS _vek
    FROM osoba AS o
    LEFT JOIN akce AS a ON mrop=1 AND YEAR(datum_od)=iniciace
    LEFT JOIN
    (SELECT mo.id_osoba,datum_od,COUNT(*) AS _ms
      -- ,GROUP_CONCAT(YEAR(datum_od) ORDER BY datum_od) AS _roky
      FROM akce AS ma
      JOIN pobyt AS mp ON mp.id_akce=ma.id_duakce
      JOIN spolu AS ms USING (id_pobyt)
      JOIN osoba AS mo USING (id_osoba)
       WHERE ma.druh=1
        AND YEAR(datum_od)<=iniciace
        -- AND ROUND(DATEDIFF(ma.datum_od,mo.narozeni)/365.2425,1)>18
        AND ROUND(IF(MONTH(mo.narozeni),DATEDIFF(ma.datum_od,mo.narozeni)/365.2425,YEAR(ma.datum_od)-YEAR(mo.narozeni)),1)>18
      GROUP BY id_osoba
      ) AS m ON m.id_osoba=o.id_osoba  -- AND m.datum_od<a.datum_od
    WHERE deleted='' AND iniciace>0 $AND
    GROUP BY iniciace
  ");
  while ( $mr && list($mrop,$ucast,$ms,$vek)= pdo_fetch_row($mr) ) {
    $celkem+= $ucast;
    $pms= round(100*$ms/$ucast);
    $tab.= "<tr><th>$mrop</th><td$styl>$ucast</td><td$styl>$ms</td><td$styl>$pms%</td><td$styl>$vek</td></tr>";
  }
  $tab.= "<tr><th>&Sigma;</th><th>$celkem</th></tr>";
  $tab.= "</table></div>";
  // kontrola položky iniciace a úžasti a akci
  $ehm= '';
  $mr= pdo_qry("
    SELECT id_osoba,YEAR(datum_od) AS _nesmer,iniciace,jmeno,prijmeni
    FROM akce AS a
    LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce AND funkce=0
    JOIN spolu AS s USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE a.mrop=1 AND deleted=''
    AND YEAR(datum_od)!=iniciace
  ");
  while ( $mr && list($ido,$nesmer,$iniciace,$jmeno,$prijmeni)= pdo_fetch_row($mr) ) {
    $ehm.= "<br>$jmeno $prijmeni ($ido) byl účastník MROP $nesmer ale má zapsáno jako iniciaci rok $iniciace";
  }
  if ( $ehm ) {
    $tab.= "<br>V datech jsou problémy:<br>$ehm";
  }
  return $msg.$tab;
}
# ------------------------------------------------------------------------------==> . sta2 mrop vliv
# rozbor podle navštěvovaných akcí
function sta2_mrop_vliv($par,$export=false) {
  $msg= "<h3>Odkud přicházejí a kam jdou</h3><i>Poznámka: starší, zjednodušená verze bez CPR aj.</i><br><br>";
  $limit= $AND= '';
//   $AND= "AND iniciace=2002 AND id_osoba=5877";
  // seznam
  $ms= array();
  $mr= pdo_qry("
    SELECT id_osoba,prijmeni,iniciace,COUNT(*)
    FROM osoba
    LEFT JOIN spolu USING (id_osoba)
    WHERE deleted='' AND iniciace>0 $AND
    -- AND id_osoba=6689
    GROUP BY id_osoba
  ");
  while ( $mr && list($ido,$name,$mrop,$spolu)= pdo_fetch_row($mr) ) {
    $ms[$ido]= (object)array('name'=>$name,'mrop'=>$mrop, 'akci'=>$spolu, 'ucast'=>0);
  }
  // vlastnosti
  $akce_muzi= "24,5,11";
  foreach ($ms as $ido=>$m) {
    $ma= pdo_qry("
      SELECT
        CASE WHEN druh=1 THEN 100 WHEN druh IN ($akce_muzi) THEN 10 ELSE 1 END AS _druh,
        COUNT(*),
        IF(MIN(IFNULL(YEAR(datum_od),9999))<=iniciace,1,0) AS _pred,
        IF(MAX(IFNULL(YEAR(datum_od),0))>iniciace,1,0) AS _po
      FROM pobyt AS p
      LEFT JOIN akce AS a ON id_akce=id_duakce
      LEFT JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      WHERE id_osoba=$ido AND spec=0 AND mrop=0 AND zruseno=0
      GROUP BY _druh ORDER BY _druh DESC
    ");
    while ( $ma && list($druh,$kolikrat,$pred,$po)= pdo_fetch_row($ma) ) {
      $m->ucast+= $druh;
      switch ($druh) {
      case 100: $m->ms_pred= $pred*$druh; $m->ms_po= $po*$druh;  break; // MS
      case  10: $m->m_pred=  $pred*$druh; $m->m_po=  $po*$druh;  break; // muži, otcové
      case   1: $m->j_pred=  $pred*$druh; $m->j_po=  $po*$druh;  break; // jiné
      case   0:   break; // žádné
      }
    }
    // první účast
    $m->pred= $m->ms_pred + $m->m_pred + $m->j_pred;
    $m->po=   $m->ms_po   + $m->m_po   + $m->j_po;
  }
//                                                         debug($ms);
  // statistický souhrn
  $muzu= count($ms);
  $ucast= $pred= $po= array();
  foreach ($ms as $ido=>$m) {
    // účastníci
    $ucast[$m->ucast]++;
    $pred[$m->pred]++;
    $po[$m->po]++;
  }
//                                                        debug($ucast,'ucast');
//                                                        debug($pred,'před');
//                                                        debug($po,'po');
  $c_pred= $c_po= $c_ucast= 0;
  $styl= " style='text-align:right'";
  $tab= "<div class='stat'><table class='stat'>
         <tr><th>typ akce</th><th>před MROP</th><th>po MROP</th><th>mimo MROP</th></tr>";
  foreach (
    array(111=>'MS+M+J',110=>'MS+M',101=>'MS+J',100=>'MS',11=>'M+J',10=>'M',1=>'J',0=>'žádná') as $k=>$i) {
    $tab.= "<tr><th>$i</th><td$styl>{$pred[$k]}</td><td$styl>{$po[$k]}</td><td$styl>{$ucast[$k]}</td></tr>";
    $c_pred+= $pred[$k];
    $c_po+= $po[$k];
    $c_ucast+= $ucast[$k];
  }
  $tab.= "<tr><th>&Sigma;</th><th$styl>$c_pred</th><th$styl>$c_po</th><th$styl>$c_ucast</th></tr>";
  $tab.= "</table>";

  $msg.= "Celkem $muzu iniciovaných mužů<br><br>";
  $msg.= $tab;
  $msg.= "<br><br>MS znamená účast na Manželských setkání, M účast na akci pro muže nebo otce,
         J účast na jiné akci";
  return $msg;
}
# ====================================================================================> . sta2 cesty
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
# par.od= rok počátku statistik
function sta2_cesty($org,$par,$title,$export=false) {
  $od_roku= isset($par->od) ? $par->od : 0;
  $par->fld= 'nazev';
  $par->tit= 'nazev';
//                                                   debug($par,"sta2_cesty(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,s,as,bs,abs,bas";
  $tits= explode(',',$tit);
  $fld= "rr,u,s,as,bs,abs,bas";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'u'=>0);
    $ida= select1("id_duakce","akce","druh=1 AND spec=0 AND zruseno=0 
      AND YEAR(datum_od)=$rrrr AND access&$org");
    if (!$ida) continue;
    $tab= akce2_info_par($ida,0,1);
    foreach (explode(',',"s,as,bs,abs,bas") as $i) {
      $clmn[$rr]['u']+= $tab[$i];
      $clmn[$rr][$i]= $tab[$i];
    }
//     $clmn[$rr]['u']+= $ida;
  }
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "u:n,as,bs,abs,bas";
  $par->txt= "Pozn. Graficky je znázorněn absolutní počet." //relativní počet vzhledem k počtu párů.;
    . "<br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet."
    . "<br><br>Význam sloupců s..bas
        <br>s = již první akce byla společná
        <br>as = napřed byl na nějaké akci muž, pak byli na společné
        <br>bs = napřed byla na nějaké akci žena, pak byli na společné
        <br>abs = napřed byl muž, potom žena, pak společně
        <br>bas = napřed byla žena, potom muž, pak společně"
    ;
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# ================================================================================> . sta2 struktura
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
# par.od= rok počátku statistik, parg.graf=1 ukázat graficky
function sta2_struktura($org,$par,$title,$export=false) {
  $od_roku= isset($par->od) ? $par->od : 0;
  $mez_k= 3.0;
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= sta2_akcnost_vps($org,$par,$title,true);
//                                                    debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,
        celkem pečounů,+pp,+po,+pg,
        dětí na kurzu,placených kočárků,dětí<$mez_k let s sebou,dětí<18 let doma,
        manželství,věk muže,věk ženy";
  $tits= explode(',',$tit);
  $fld= "rr,u,n,p,v,vo,vs,pec,pp,po,pg,d,K,k,x,m,a,b";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'u'=>0, 'n'=>0, 'p'=>0, 'v'=>0, 'vo'=>0, 'vs'=>0);
    $rows= count($tab->clmn);
    for ($n= 1; $n<=$rows; $n++) {
      if ( ($xrr= $tab->clmn[$n][$rr]) ) {
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
      $suma[$rr]+= isset($clmn[$rr][$fld]) ? $clmn[$rr][$fld] : 0;
    }
  }
  // doplnění informací o rodinách
  $rod= sta2_rodiny($org,$od_roku,$mez_k);
  // doplnění informací o pečounech
  $pecs= sta2_pecouni_simple($org);
//                                         debug($rod,"rodiny");
  foreach ($rod as $rok=>$r) {
    if ( $rok<$od_roku ) continue;
    $rr= substr($rok,-2);
    $clmn[$rr]['u']= $r['r'];
    $clmn[$rr]['d']= $r['d'];
    $clmn[$rr]['k']= $r['k'];
    $clmn[$rr]['K']= $r['K'];
    $clmn[$rr]['x']= $r['x'];
    $clmn[$rr]['m']= $r['m'];
    $clmn[$rr]['a']= $r['a'];
    $clmn[$rr]['b']= $r['b'];
    $clmn[$rr]['pec']= isset($pecs[$rok]['p']) ? $pecs[$rok]['p'] : 0;
    $clmn[$rr]['pp']=  isset($pecs[$rok]['pp']) ? $pecs[$rok]['pp'] : 0;
    $clmn[$rr]['po']=  isset($pecs[$rok]['po']) ? $pecs[$rok]['po'] : 0;
    $clmn[$rr]['pg']=  isset($pecs[$rok]['pq']) ? $pecs[$rok]['pg'] : 0;
  }
  // smazání prázdných
  foreach ($clmn as $r=>$c) {
    if ( !isset($c['x']) ) unset($clmn[$r]);
  }

//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  // Popis sloupců a jejich datových zdrojů
  // - kočárků - pobyt.kocarek tzn. z plateb
  // - pečounů - 
  $par->tit= $tit;
  $par->fld= $fld;
  if ( $par->graf ) {
    $par->grf= "u:n,p,v,vo,vs,pec,d";
    $par->txt= "Graficky je znázorněn absolutní počet."; //relativní počet vzhledem k počtu párů.;
  }
  if (!isset($par->txt)) $par->txt= '';
  $par->txt.= "<br><br>Zkratky názvů sloupců: pp=pomocní pečovatelé, po=osobní pečovatelé, pg=děti skupiny G";
  $par->txt.= "<br><br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------==> . sta2 pecouni_simple
function sta2_pecouni_simple($org) { trace();
  $clmn= array();
  $qry= " SELECT p.funkce, s.pfunkce, YEAR(datum_od)
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          WHERE (p.funkce=99 OR (p.funkce NOT IN (9,10,13,14,15,99) AND s.pfunkce IN (4,5,8))) 
            AND a.druh=1 AND a.access & $org";
  $res= pdo_qry($qry);
  while ( $res && (list($f,$pf,$rok)= pdo_fetch_row($res)) ) {
    if (!isset($clmn[$rok])) $clmn[$rok]= array('p'=>0,'pp'=>0,'po'=>0,'pg'=>0);
    $clmn[$rok]['p']+=  $f==99 ? 1 : 0;
    $clmn[$rok]['pp']+= $pf==4 ? 1 : 0;
    $clmn[$rok]['po']+= $pf==5 ? 1 : 0;
    $clmn[$rok]['pg']+= $pf==8 ? 1 : 0;
  }
  return $clmn;
}
# --------------------------------------------------------------------------------- sta2 akcnost_vps
# generování přehledu akčnosti VPS
function sta2_akcnost_vps($org,$par,$title,$export=false) {  trace();
  // dekódování parametrů
  $roky= $froky= '';
  for ($r=1990;$r<=date('Y');$r++) {
    $roky.= ','.substr($r,-2);
    $froky.= ','.substr($r,-2).':3';
  }
  $tits= explode(',',$tit= $par->tit.$froky);
  $flds= explode(',',$fld= $par->fld.$roky);
  $HAVING= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $fce= "ovxkphmx";
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $qry="SELECT COUNT(*) AS _ucasti, r.nazev,r.obec,
          SUM(p.funkce) AS _vps,
          GROUP_CONCAT(DISTINCT CONCAT(YEAR(a.datum_od),':',p.funkce) ORDER BY datum_od SEPARATOR '|') AS _x,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') AS jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') AS jmeno_z
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r USING(id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.funkce IN (0,1) AND a.access & $org
        GROUP BY r.id_rodina $HAVING
        ORDER BY r.nazev
        ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_jmeno':                            // kolektivní člen
        $clmn[$n][$f]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}";
        break;
      default:
        $clmn[$n][$f]= isset($x->$f) ? $x->$f : '';
      }
    }
    // rozbor let
    foreach(explode('|',$x->_x) as $rf) {
      list($xr,$xf)= explode(':',$rf);
      $clmn[$n][substr($xr,-2)]= $xf < strlen($fce) ? substr($fce,$xf,1) : '?';
    }
  }
//                                                 debug($clmn,"clmn");
  $par->tit= $tit;
  $par->fld= $fld;
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- sta2 table_graph
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function sta2_table_graph($par,$tits,$flds,$clmn,$export=false) {
  global $ezer_root;
  $result= (object)array('par'=>$par);
  if ( isset($par->grf) ) {
    list($norm,$grf)= explode(':',$par->grf);
  }
  $skin= $_SESSION[$ezer_root]['skin'];
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $n= 0;
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
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
        // přidání grafu
        $g= '';
        if ( isset($par->grf) && strpos(",$grf,",",$f,")!==false ) {
          //$w= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;     -- relativní počet
          $w= isset($c[$f]) ? $c[$f] : 0;
          $g= "<div class='curr_akce' style='height:4px;width:{$w}px;float:left;margin-top:5px'>";
        }
        if (isset($c[$f])) {
          $align= is_numeric($c[$f]) || preg_match("/\d+\.\d+\.\d+/",$c[$f]) ? "right" : "left";
          $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
        }
        else {
          $tab.= "<td>$g</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>
      $n řádků<br><br>{$par->txt}</div>";
  }
//                                                 debug($result);
  return $result;
}
# ---------------------------------------------------------------------------------==> . sta2 rodiny
# clmn: rok -> r:rodin, d:dětí na akci, x:dětí<18 doma, m:délka manželství, a,b:věk muže, ženy
# - $mez_k je věková hranice dělící děti na (asi) kočárkové resp. postýlkové
function sta2_rodiny($org,$rok=0,$mez_k=2.0) { trace();
  $clmn= array();
  $ms= array();
  // ms => r=rodin, d=dětí na akci, D=dětí mladších 18 v rodině,
  //       va=věk muže, na=počet mužů s věkem, vb=věk ženy, nb=.., vm=délka manželství, nm=..
//  $rok= 2016; // *****************************************
  $HAVING= $rok ? "HAVING _rok>=$rok" : '';
  $rx= pdo_qry("
    SELECT id_akce, YEAR(datum_od) AS _rok,
      COUNT(id_osoba) AS _clenu, COUNT(id_spolu) AS _spolu,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < 18 AND id_spolu,1,0)) AS _sebou,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < 18,1,0)) AS _deti,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < $mez_k 
        AND id_spolu,1,0)) AS _sebou_k, kocarek,
      SUM(IF(t.role='a',IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),0)) AS _vek_a,
      SUM(IF(t.role='b',IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),0)) AS _vek_b,
      -- IF(r.datsvatba,DATEDIFF(a.datum_od,r.datsvatba)/365.2425,
        -- IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
      IF(r.datsvatba,IF(MONTH(r.datsvatba),DATEDIFF(a.datum_od,r.datsvatba)/365.2425,YEAR(a.datum_od)-YEAR(r.datsvatba)),
        IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
    FROM pobyt AS p
    JOIN akce AS a ON id_akce=id_duakce
    JOIN rodina AS r ON id_rodina=i0_rodina
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    LEFT JOIN spolu USING (id_pobyt,id_osoba)
    WHERE a.druh=1 AND (a.access & $org) AND p.funkce IN (0,1,2,5) -- AND p.funkce IN (0,1) 
    --  AND id_pobyt=50904
    GROUP BY id_pobyt $HAVING
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $r= $x->_rok;
    if (!isset($ms[$r])) 
      $ms[$r]= array('r'=>0,'d'=>0,'k'=>0,'K'=>0,'D'=>0,'va'=>0,'na'=>0,'vb'=>0,'nb'=>0,'vm'=>0,'nm'=>0);
    $ms[$r]['r']++;
    $ms[$r]['d']+= $x->_sebou;
    $ms[$r]['k']+= $x->_sebou_k;
    $ms[$r]['K']+= $x->kocarek;
    $ms[$r]['D']+= $x->_deti;
    if ( $x->_vek_a && $x->_vek_a<100 ) {
      $ms[$r]['va']+= $x->_vek_a;
      $ms[$r]['na']++;
    }
    if ( $x->_vek_b && $x->_vek_b<100 ) {
      $ms[$r]['vb']+= $x->_vek_b;
      $ms[$r]['nb']++;
    }
    if ( $x->_vek_m && $x->_vek_m!=0 ) {
      $ms[$r]['vm']+= $x->_vek_m;
      $ms[$r]['nm']++;
    }
  }
  foreach (array_keys($ms) as $r) {
    $clmn[$r]['r']= $ms[$r]['r'];
    $clmn[$r]['d']= $ms[$r]['d'];
    $clmn[$r]['k']= $ms[$r]['k'];
    $clmn[$r]['K']= $ms[$r]['K'];
    $clmn[$r]['x']= $ms[$r]['D'] - $ms[$r]['d'];
    $clmn[$r]['m']= round($ms[$r]['nm'] ? $ms[$r]['vm']/$ms[$r]['nm'] : 0);
    $clmn[$r]['a']= round($ms[$r]['na'] ? $ms[$r]['va']/$ms[$r]['na'] : 0);
    $clmn[$r]['b']= round($ms[$r]['nb'] ? $ms[$r]['vb']/$ms[$r]['nb'] : 0);
  }
//                                                         debug($clmn,"sta2_rodiny($org,$rok)");
  return $clmn;
}
# --------------------------------------------------------------------------------==> . sta2 pecouni
function sta2_pecouni($org) { trace();
//   case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
  # _pec,_sko,_proc
  $clmn= array();
  list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1 AND access&$org");
//  $od=$do=2018;
  for ($rok=$od; $rok>=$do; $rok--) {
    $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= $akci ? "$akci školení" : '';
    $info= akce2_info($kurz,0,1); //muzi,zeny,deti,peco,rodi,skup,pp,po,pg
    // získání dat
    $_pec= $_sko= $_proc= $_pecN= $_skoN= $_procN= 0;
    $data= array();
    _akce2_sestava_pecouni($data,$kurz);
//    $_pec= count($data);
//    if ( !$_pec ) continue;
    if ( !count($data) ) continue;
    foreach ($data as $d) {
      $skoleni= 0;
      $sko= array_unique(preg_split("/\s+/",$d['_skoleni'], -1, PREG_SPLIT_NO_EMPTY));
      $slu= array_unique(preg_split("/\s+/",$d['_sluzba'],  -1, PREG_SPLIT_NO_EMPTY));
      $ref= array_unique(preg_split("/\s+/",$d['_reflexe'], -1, PREG_SPLIT_NO_EMPTY));
      $leto= $slu[0];
      // počty různých typů pečounů
      $_pec++;
      // výpočet školení všech
      $skoleni+= count($sko);
      foreach ($ref as $r) {
        if ( $r<$leto ) 
          $skoleni++;
      }
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
    // aserce na pečouny
    $err= $_pec!=$info->peco+$info->_pp+$info->_po+$info->_pg
        ? "$_pec &ne; {$info->peco}+{$info->_pp}+{$info->_po}+{$info->_pg}" : '';
    // zobrazení výsledků
    $clmn[]= array('_rok'=>$rok,'_rodi'=>$info->rodi,'_deti'=>$info->deti,
      '_pec'=>$info->peco,'_sko'=>$_sko,'_proc'=>$_proc,
      '_pp'=>$info->_pp,'_po'=>$info->_po,'_pg'=>$info->_pg,'_celk'=>$_pec,
      '_pecN'=>$_pecN,'_skoN'=>$_skoN,'_procN'=>$_procN,'_note'=>$note,'chyba'=>$err);
//       if ( $rok==2014) break;
  }
  return $clmn;
}
# ==================================================================================> . sta2 sestava
# sestavy pro evidenci
function sta2_sestava($org,$title,$par,$export=false) { trace();
//                                                 debug($par,"sta2_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  $note_before= '';
  // dekódování parametrů
  $tits= $par->tit ? explode(',',$par->tit) : array();
  $flds= $par->fld ? explode(',',$par->fld) : array();
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {

  # Sestava údajů o akcích: LK MS, Glotrach, Křižanov, Nesměř ap.
  #  item {title:'Přehled větších vícedenních akcí'  ,par:°{typ:'4roky',rok:0,xls:'Excel',pdf:0
  #    ,dsc:'Sestava ukazuje údaje o účastnících na vybraných akcích za uplynulé 4 roky.<br>'
  #    ,tit:'věk:4,účastníků:10,pečovatelů:10',fld:'_vek,_uca,_pec',ord:'_vek'}}
  case '4roky':     // -----------------------------------==> .. 4 roky velkých akcí
    $roky= (date('Y')-4)." AND ".(date('Y')-1);
    $tits= explode(',',
      'rok:6,dnů:6,R/J:6,místo akce:12,název akce:22,celkem účastníků a dětí (bez týmu a pečounů a chův):10,'
     . 'průměrný věk dospělých:10,dospělých mužů:10,dospělých žen:10,'
     . 'dětí na akci:8,~ průměrně na rodinu:10,dětí doma (do 18):8,celkem mají účastníci dětí,~ průměrně na rodinu:10,'
     . '+ počet chův na akci:8,+ počet pečounů na akci:8,průměrný věk pečounů:9,(SS):5,(ID):5');
    $flds= explode(',',
      'rok,dnu,rj,misto,nazev,n_all,a_vek,muzu,zen,'
    . 'deti,p_deti,r_dit18,r_deti,pr_deti,n_chu,n_pec,a_vek_pec,ucet,ID');
    // kritéria akcí
    $druh_r= $org==2 ? '200,230'          : '412';                      // MS
    $druh_j= $org==2 ? '300,301,310,410'  : '302';                      // muži, ženy
    $druh= $druh_r . ( $druh_j ? ",$druh_j" : '');
//g    $ss=     $org==2 ? "ciselnik_akce"    : "g_kod";
    $ss=     "ciselnik_akce";
    $test= "1";
//     $test= "id_akce=694";
//     $test= "id_akce IN (694,738)";
//     $test= "YEAR(a.datum_od)=2013";
    $rx= pdo_qry("
      SELECT id_duakce,a.datum_od,$ss,nazev,misto,YEAR(a.datum_od),DATEDIFF(a.datum_do,a.datum_od),
        x.n_all,a_vek,n_mzu,n_zen,n_dti,n_ote,n_mat,n_dit,n_chu,n_pec,a_vek_pec,n_nul,_rr
      FROM akce AS a
      LEFT JOIN join_akce AS aj ON aj.id_akce=a.id_duakce
      LEFT JOIN (
        SELECT id_akce, COUNT(*) AS n_all, GROUP_CONCAT(DISTINCT xp.i0_rodina) AS _rr,
          ROUND(SUM(IF(funkce IN (0,1) AND ROUND(
              IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18,
              IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),0))
              / SUM(funkce IN (0,1) AND IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18,1,0)))
            AS a_vek,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)<18,1,0)) AS n_dti,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18 AND xo.sex=1,1,0)) AS n_mzu,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18 AND xo.sex=2,1,0)) AS n_zen,
          SUM(IF(xst.role='a',1,0)) AS n_ote, SUM(IF(xst.role='b',1,0)) AS n_mat,
          SUM(IF(xst.role='d',1,0)) AS n_dit, SUM(IF(xst.role NOT IN ('a','b','d'),1,0)) AS n_chu,
          SUM(IF(ISNULL(xst.role),1,0)) AS n_nul,
          SUM(IF(funkce=99,1,0)) AS n_pec,
          ROUND(SUM(IF(funkce=99,IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),0)) / SUM(IF(funkce=99,1,0)))
            AS a_vek_pec
        FROM pobyt AS xp
        JOIN akce  AS xa ON xa.id_duakce=xp.id_akce
        JOIN spolu AS xs USING (id_pobyt)
        JOIN osoba AS xo ON xo.id_osoba=xs.id_osoba
        LEFT JOIN tvori  AS xst ON xst.id_osoba=xs.id_osoba AND IF(xp.i0_rodina,xst.id_rodina=xp.i0_rodina,0)
        WHERE funkce IN (0,1,99)
        GROUP BY id_akce
      ) AS x ON x.id_akce=id_duakce
      WHERE a.access=$org AND $ss IN ($druh)
        AND YEAR(a.datum_od) BETWEEN $roky AND DATEDIFF(a.datum_do,a.datum_od)>0
        AND $test
      ORDER BY a.datum_od,ciselnik_akce
    ");
    while ( $rx && (list(
        $ida,$datum_od,$ucet,$nazev,$misto,$rok,$dnu,
        $n_all,$a_vek,$n_mzu,$n_zen,$n_dti,$n_ote,$n_mat,$n_dit,$n_chu,$n_pec,$a_vek_pec,$n_nul,$rr
      )= pdo_fetch_row($rx)) ) {
      $r_deti= $r_deti18= 0;
      // rozhodnutí o typu akce: rodiny / jednotlivci
      if ( strpos(" $druh_r",$ucet) ) {
        // dopočet údajů rodin
        if ( $rr ) {
          $rs= pdo_qry("
            SELECT SUM(IF(role='d',1,0)),
              -- SUM(IF(ROUND(DATEDIFF('$datum_od',o.narozeni)/365.2425,1)<18,1,0)) AS _deti
              SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum_od',o.narozeni)/365.2425,YEAR('$datum_od')-YEAR(o.narozeni)),1)<18,1,0)) AS _deti
            FROM rodina AS r
              JOIN tvori AS t USING (id_rodina)
              JOIN osoba AS o USING (id_osoba)
            WHERE id_rodina IN ($rr)
            GROUP BY id_rodina
          ");
          while ( $rs && (list($deti,$deti18)= pdo_fetch_row($rs)) ) {
            $r_deti+= $deti;
            $r_deti18+= $deti18;
          }
        }
        $p_deti= round($n_mat ? $n_dit/$n_mat : 0,2);
        $pr_deti= round($n_mat ? $r_deti/$n_mat : 0,2);;
        $clmn[$ida]= array( // rodiny
          'rok'=>$rok, 'dnu'=>$dnu, 'rj'=>'R', 'nazev'=>"$nazev", 'misto'=>$misto,
          'n_all'=>$n_all-$n_pec-$n_chu, 'a_vek'=>$a_vek,
          'muzu'=>$n_ote, 'zen'=>$n_mat, 'deti'=>$n_dit, 'p_deti'=>$p_deti,
          'n_chu'=>$n_chu, 'n_pec'=>$n_pec, 'a_vek_pec'=>$a_vek_pec,
          'n_nul'=>$n_nul,
          'r_deti'=>$r_deti, 'pr_deti'=>$pr_deti, 'r_dit18'=>$r_deti18-$n_dti,
          'a_vek'=>$a_vek,
          'ucet'=>$ucet, 'ID'=>$ida
        );
      }
      else {
        $clmn[$ida]= array( // jednotlivci
          'rok'=>$rok, 'dnu'=>$dnu, 'rj'=>'J', 'nazev'=>"$nazev", 'misto'=>$misto,
          'a_vek'=>$a_vek,
          'n_all'=>$n_all,
          'muzu'=>$n_mzu, 'zen'=>$n_zen, 'deti'=>$n_dti,
          'n_chu'=>$n_chu, 'n_nul'=>$n_nul,
          'a_vek'=>$a_vek,
          'ucet'=>$ucet, 'ID'=>$ida
        );
      }
    }
    // náhrada nul a test součtu muzu+zen+deti=n_all
    $note_before= "";
    foreach($clmn as $j=>$row) {
      $suma= $row['muzu']+$row['zen']+$row['deti'];
      $pocet= $row['n_all'];
      if ( $suma != $pocet ) {
        $note_before.= "<br>U akce {$row['ID']} nesouhlasí počet mužů+žen+dětí ($suma) s účastníky celkem ($pocet)";
      }
      foreach($row as $i=>$value) {
        if ( !$value ) $clmn[$j][$i]= '';
      }
    }
    if ( $note_before ) $note_before= "POZOR!$note_before<br><br>";
//                                                debug($clmn,"clmn");
    break;

  # Sestava pro export jmen a emailů všech evidovaných
  case 'maily':     // -------------------------------------==> .. maily
    $tits= array("jmeno:15","prijmeni:15","email:30","neposílat:10");
    $flds= array('jmeno','prijmeni','_email','nomail');
    $rx= pdo_qry("SELECT
        o.id_osoba,jmeno,prijmeni,
        IF(o.kontakt,o.email,IFNULL(r.emaily,'')) AS _email,nomail
      FROM osoba AS o
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      GROUP BY o.id_osoba
      HAVING _email!=''
      ORDER BY o.prijmeni,o.jmeno
      -- LIMIT 10
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
      $clmn[]= $x;
    }
    break;

  # Sestava pečounů na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'pecujici':     // -----------------------------------==> .. pecujici
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $tits= array("pečovatel:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10","1.školení:10",
                 "č.člen od:10","bydliště:25","narození:10","(ID osoby)");
    $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    $rx= pdo_qry("SELECT
        o.id_osoba,jmeno,prijmeni,o.obec,narozeni,
        MIN(CONCAT(t.role,IF(o.adresa,o.obec,r.obec))) AS _obec,
        MIN(IF(druh=1,YEAR(datum_od),9999)) AS OD,
        MAX(IF(druh=1,YEAR(datum_od),0)) AS DO,
        CEIL(CHAR_LENGTH(
          GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=99,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
        MIN(IF(druh=7,YEAR(datum_od),9999)) AS _skoleni,
        GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
        GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka)
          ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce as a ON id_akce=id_duakce
      LEFT JOIN dar AS od ON o.id_osoba=od.id_osoba AND od.deleted=''
      LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
      LEFT JOIN rodina AS r ON t.id_rodina=r.id_rodina
      WHERE p.funkce=99 AND a.access&$org
        -- AND o.prijmeni LIKE 'D%'
        AND druh IN (1,7)
      GROUP BY o.id_osoba
      -- HAVING
        -- _skoleni<9999 AND
        -- DO>=$hranice
      ORDER BY o.prijmeni");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
      // číslování certifikátů
      $skola= $x->_skoleni==9999 ? 0 : $x->_skoleni;
      $c1= '';
      if ( $skola ) {
        if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
        $cert[$skola]++; $c1= "pec_$skola/{$cert[$skola]}";
      }
      // ohlídání období
      if ( $x->DO<$hranice ) continue;
      // rozbor úkonů
      $_clen_od= $_cinny_od= $_prisp= $prisp_letos= $_dary= 0;
      foreach(explode('|',$x->_ukony) as $uddc) {
        list($u,$d1,$d2,$c)= explode(':',$uddc);
        switch ($u) {
        case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
        case 'd': if ( $d1==$rok ) $_dary+= $c; break;
        case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
        case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
        }
      }
      $cclen= $_cinny_od ?: '-';
      // odpověď
      $clmn[]= array(
        'jm'=>"{$x->prijmeni} {$x->jmeno}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
        'vps_i'=>$skola ?: '-', 'cert'=>$c1,
        'clen'=>$cclen,
        'byd'=>$x->_obec ? substr($x->_obec,1) : $x->obec,
        'nar'=>substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2),
        '^id_osoba'=>$x->id_osoba
      );
    }
//                                                 debug($clmn,"$hranice");
    break;

  # Sestava historie VPS - varianta pro YS
  case 'slouzici2':    // -------------------------------------==> .. slouzici2
    return vps_historie($org,$par,$export);
    break;
  
  # Sestava sloužících na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'slouzici':     // -------------------------------------==> .. slouzici
    global $VPS;
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $vps1= $org==1 ? '3,17' : '3';
    $order= 'r.nazev';
    if ( $par->podtyp=='pary' ) {
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","(ID)");
      $flds= array('jm','od','n','do','vps_i','clen','^id_rodina');
    }
    else if ( $par->podtyp=='skupinky' ) {
      $behy= isset($par->behy) ? $par->behy : 1;
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10");
      $flds= array('jm','od','n','do','vps_i');
    }
    else if ( $par->podtyp=='kulatiny' ) {
      $tits= array("jméno:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","narození:10:d","svatba:10:d","roků:7",
                    "telefon:20","email:30","(ID)");
      $flds= array('jm','od','n','do','vps_i','nar','svatba','roku','telefon','email','^id_osoba');
      $letos= date('Y');
      $kulate= substr($letos,3,1);
      $pulkulate= ($kulate+5) % 10;
      $order= 'MONTH(o.narozeni),DAY(o.narozeni)';
    }
    else { // osoby
      $tits= array("jméno:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","bydliště:25","narození:10","(ID)");
      $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    }
    $rx= pdo_qry("SELECT
        r.id_rodina,r.nazev,r.svatba,r.datsvatba,r.rozvod,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.umrti,'') SEPARATOR '') as umrti_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_m,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.umrti,'') SEPARATOR '') as umrti_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') as jmeno_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_z,
        MIN(IF(druh=1 AND funkce=1,YEAR(datum_od),9999)) AS OD,
        CEIL(CHAR_LENGTH(
          GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=1,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
        MAX(IF(druh=1 AND funkce=1,YEAR(datum_od),0)) AS DO,
        MIN(IF(druh IN ($vps1),YEAR(datum_od),9999)) as VPS_I,
        GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
        GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka)
          ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
      FROM rodina AS r
      JOIN pobyt AS p
      JOIN akce as a ON id_akce=id_duakce
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN dar AS od ON o.id_osoba=od.id_osoba AND od.deleted=''
      WHERE spec=0 AND zruseno=0 AND o.deleted=''
        AND r.id_rodina=i0_rodina AND a.access&$org
        -- AND r.nazev LIKE 'Šmí%'
        AND druh IN (1,$vps1) 
      GROUP BY r.id_rodina
      -- HAVING -- bereme vše kvůli číslům certifikátů - vyřazuje se až při průchodu
        -- VPS_I<9999 AND
        -- DO>=$hranice
      ORDER BY $order");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
      // číslování certifikátů
      $skola= $x->VPS_I==9999 ? 0 : $x->VPS_I;
      $c1= $c2= '';
      if ( $skola ) {
        if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
        $cert[$skola]++; $c1= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
        $cert[$skola]++; $c2= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
      }
      // ohlídání období
      if ( $x->DO<$hranice ) continue;
      // rozbor úkonů
      $_clen_od= $_cinny_od= $_prisp= $prisp_letos= $_dary= 0;
      foreach(explode('|',$x->_ukony) as $uddc) {
        list($u,$d1,$d2,$c)= explode(':',$uddc);
        switch ($u) {
        case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
        case 'd': if ( $d1==$rok ) $_dary+= $c; break;
        case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
        case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
        }
      }
      $cclen= $_cinny_od ?: '-';
      // odpověď
      if ( $par->podtyp=='pary' ) {
        $clmn[]= array(
          'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
          'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-',
          'clen'=>$cclen,'^id_rodina'=>$x->id_rodina
        );
      }
      elseif ( $par->podtyp=='skupinky' ) {
        $clmn[]= array(
          'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
          'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-'
        );
      }
      elseif ( $par->podtyp=='kulatiny' ) {
        // zjistíme telefon
        $muz= db2_osoba_kontakt($x->id_m);
        $zena= db2_osoba_kontakt($x->id_z);
        if (substr($x->narozeni_m,3,1)==$kulate && !$x->umrti_m) {
          $roku= $letos - substr($x->narozeni_m,0,4);
          $kdy= sql_date1($x->narozeni_m);
          $order= substr($x->narozeni_m,5,2).substr($x->narozeni_m,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->prijmeni_m} {$x->jmeno_m}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'nar'=>$kdy, 'roku'=>$roku, 'telefon'=>$muz->telefon, 'email'=>$muz->email, 
            '^id_osoba'=>$x->id_m
          );
        }
        if (substr($x->narozeni_z,3,1)==$kulate && !$x->umrti_z) {
          $roku= $letos - substr($x->narozeni_z,0,4);
          $kdy= sql_date1($x->narozeni_z);
          $order= substr($x->narozeni_z,5,2).substr($x->narozeni_z,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->prijmeni_z} {$x->jmeno_z}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'nar'=>$kdy, 'roku'=>$roku, 'telefon'=>$zena->telefon, 'email'=>$zena->email, 
            '^id_osoba'=>$x->id_z
          );
        }
        if (substr($x->datsvatba,3,1)==$pulkulate && !$x->rozvod && !$x->umrti_m && !$x->umrti_z ) {
          $tel= $muz->telefon==$zena->telefon ? $muz->telefon
              : "{$muz->telefon}, {$zena->telefon}";
          $mai= $muz->email==$zena->email ? $muz->email
              : "{$muz->email}, {$zena->email}";
          $roku= $letos - substr($x->datsvatba,0,4);
          $kdy= sql_date1($x->datsvatba);
          $order= substr($x->datsvatba,5,2).substr($x->datsvatba,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'svatba'=>$kdy, 'roku'=>$roku, 'telefon'=>$tel, 'email'=>$mai,
            '^id_osoba'=>$x->id_z
          );
        }
        usort($clmn,function($a,$b){return $a['order']>$b['order'];});
      }
      else { // osoby
        $clmn[]= array(
          'jm'=>"{$x->prijmeni_m} {$x->jmeno_m}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-', 'cert'=>$c1, 'clen'=>$cclen, 'byd'=>$x->obec_m,
          'nar'=>substr($x->narozeni_m,2,2).substr($x->narozeni_m,5,2).substr($x->narozeni_m,8,2),
          '^id_osoba'=>$x->id_m
        );
        $clmn[]= array(
          'jm'=>"{$x->prijmeni_z} {$x->jmeno_z}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-', 'cert'=>$c2, 'clen'=>$cclen, 'byd'=>$x->obec_z,
          'nar'=>substr($x->narozeni_z,2,2).substr($x->narozeni_z,5,2).substr($x->narozeni_z,8,2),
          '^id_osoba'=>$x->id_z
        );
      }
    }
//                                                 debug($clmn,"$hranice");
    break;

  # Sestava přednášejících na letních kurzech, rok= kolik let dozadu (0=jen letos)
  case 'prednasejici': // -----------------------------------==> .. prednasejici
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
      $rx= pdo_qry("SELECT prednasi,YEAR(a.datum_od) AS _rok,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          p.pouze,r.nazev
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,t.id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.prednasi=$pr AND YEAR(a.datum_od) BETWEEN $od AND $do AND a.access&$org
        GROUP BY id_pobyt -- ,_rok
        ORDER BY _rok DESC");
      while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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
  case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
    # _pec,_sko,_proc
    $clmn= sta2_pecouni($org);
    break;

  # Sestava ukazuje celkový počet účastníků resp. pečovatelů na akcích letošního roku,
  # rozdělený podle věku. Účastník resp. pečovatel je započítán jen jednou,
  # bez ohledu na počet akcí, jichž se zúčastnil
  case 'ucast-vek': // ---------------------------------------------==> .. ucast-vek
    $rok= date('Y')-$par->rok;
    $rx= pdo_qry("
      SELECT YEAR(a.datum_od)-YEAR(o.narozeni) AS _vek,MAX(p.funkce) AS _fce
      FROM osoba AS o
      JOIN spolu AS s USING(id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce  AS a ON id_akce=id_duakce
      WHERE o.deleted='' AND YEAR(datum_od)=$rok AND a.access&$org
      GROUP BY o.id_osoba
      ORDER BY $par->ord
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
      $vek= $x->_vek==$rok ? '?' : $x->_vek;    // ošetření nedefinovaného data narození
      if ( !isset($clmn[$vek]) ) $clmn[$vek]= array('_vek'=>$vek,'_uca'=>0,'_pec'=>0);
      if ( $x->_fce==99 )
        $clmn[$vek]['_pec']++;
      else
        $clmn[$vek]['_uca']++;
    }
    break;

  # Seznam obsahuje účastníky akcí v posledních letech (parametr 'parm' určuje počet let zpět) —
  case 'adresy': // -------------------------------------------------------==> .. adresy
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
    $rx= pdo_qry("
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
        JOIN akce  AS a ON id_akce=id_duakce AND spec=0 AND zruseno=0 
      WHERE o.deleted='' AND YEAR(narozeni)<$rok18 AND a.access&$org
        AND YEAR(datum_od)>=$rok AND spec=0 AND zruseno=0 
        -- AND o.id_osoba IN(3726,3727,5210)
        -- AND o.id_osoba IN(4537,13,14,3751)
        -- AND o.id_osoba IN(4503,4504,4507,679,680,3612,4531,4532,206,207)
        -- AND id_duakce=394
      GROUP BY o.id_osoba HAVING _role!='p' $AND
      ORDER BY _order
      -- LIMIT 10
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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
    return sta2_table($tits,$flds,$clmn,$export,null,$note_before);
}
# --------------------------------------------------------------------------------- sta2 excel_subst
function sta2_sestava_adresy_fill($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# -----------------------------------------------------------------------------------=> . sta2 table
function sta2_table($tits,$flds,$clmn,$export=false,$row_numbers=false,$note='') {  trace();
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
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
      $id_len_f= explode(':',$idw);
      $id= $id_len_f[0];
      $f= isset($id_len_f[2]) ? $id_len_f[2] : '';
      $ths.= "<th>$id</th>";
      if ( $f ) $fmt[$flds[$i]]= $f;
    }
    foreach ($clmn as $i=>$c) {
      $c= (array)$c;
      $tab.= "<tr>";
      if ( $row_numbers )
        $tab.= "<th>".($i+2)."</th>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        elseif ( is_numeric($c[$f]) || $fmt[$f]=='d' )
          $tab.= "<td style='text-align:right'>{$c[$f]}</td>";
        else {
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
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
# obsluha různých forem výpisů karet AKCE
# ---------------------------------------------------------------------------------------- sta2 excel
# ASK
# generování statistické sestavy do excelu
function sta2_excel($org,$title,$par,$tab=null) {       trace();
//                                                         debug($par,"sta2_excel($org,$title,...)");
  // získání dat
  if ( !$tab ) {
    $tab= sta2_sestava($org,$title,$par,true);
    $title= $par->title ?: $title;
  }
  // vlastní export do Excelu
  return sta2_excel_export($title,$tab);
}
# ---------------------------------------------------------------------------==> . sta2 excel_export
# local
# generování tabulky do excelu
function sta2_excel_export($title,$tab) {  //trace();
//                                         debug($tab,"sta2_excel_export($title,tab)");
  global $xA, $xn, $ezer_version;
  $result= (object)array('_error'=>0);
  $html= '';
  $title= str_replace('&nbsp;',' ',$title);
  $subtitle= "ke dni ".date("j. n. Y");
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
    list($id,$w,$f,$s)= array_merge(explode(':',$idw),array('','',''));      // název sloupce : šířka : formát : suma
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
    $c= (array)$c;
    $xls.= "\n";
    $lc= 0;
//     foreach ($c as $id=>$val) { -- míchalo sloupce
    foreach ($tab->flds as $id) {
      $val= $c[$id];
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","sta2_excel_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,"\n\r","  ");
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          case 't': $format.= ' text'; break;
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
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
//   $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xlsx' target='xlsx'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- sta2 excel_subst
function sta2_excel_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
/** =========================================================================================> ELIM2 **/
# --------------------------------------------------------------------------------- elim2_split_keys
# ASK
# rozdělí klíče v řetězci pro elim_rodiny na dvě půlky
function elim2_split_keys($keys) {
  $ret= (object)array('c1'=>'','c2'=>'');
  $k1= $k2= array();
  foreach (explode(';',$keys) as $cs) {
    $cs= explode(',',$cs);
    $c0= array_shift($cs);
//                                                         debug($cs,$c0);
    if ( !in_array($c0,$k2) ) {
      $k1[]= $c0;
    }
    foreach ($cs as $c) {
      if ( !in_array($c,$k1) && !in_array($c,$k2) ) {
        $k2[]= $c;
      }
    }
  }
  $ret->c1= implode(',',$k1);
  $ret->c2= implode(',',$k2);
//                                                         debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_differ
# do _track potvrdí, že $id_orig,$id_copy jsou různé osoby nebo rodiny
function elim2_differ($id_orig,$id_copy,$table) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // zápis o neztotožnění osob/rodin do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_orig,'','r','různé od',$id_copy)");    // r=různost
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_copy,'','r','různé od',$id_orig)");    // r=různost
end:
  return $ret;
}
# ---------------------------------------------------------------------------------==> . elim2_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL
# a kopii poznačí jako smazanou
function elim2_osoba($id_orig,$id_copy) { //trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori
  $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_osoba=$id_copy");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_oso=$id_copy");
  query("UPDATE platba SET id_oso=$id_orig WHERE id_oso=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= (int)$access_orig | (int)$access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $info= "access:$access_orig|$access_copy;tvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'osoba','d','$info',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");    // x=smazání
  // id pro nastavení v browse
  $ret->ido= $id_orig;
  // pokud je orig ve více rodinách navrhni další postup
  $idrs= array();
  $rr= pdo_qry("SELECT id_rodina FROM tvori AS ot JOIN tvori AS tr USING (id_rodina)
                  WHERE ot.id_osoba=$id_orig GROUP BY id_rodina ORDER BY COUNT(*) DESC");
  while ($rr && (list($idr)= pdo_fetch_row($rr)) ) {
    $idrs[]= $idr;
  }
  $nrod= count($idrs);
  if ( $nrod>2 ) { // více jak 2 rodiny
    $ret->msg= "osoba $id_orig se vyskytuje ve $nrod rodinách, to je zapotřebí po 'Zpět' pořešit!";
  }
  elseif ( $nrod==2 ) { // právě 2 rodiny
    $ret->msg= "osoba $id_orig se vyskytuje ve dvou rodinách, nabídnu nástroj k řešení";
    $ret->idr1= $idrs[0];
    $ret->idr2= $idrs[1];
  }
end:
//                                                        debug($ret,"elim2_osoba nrod=$nrod");
  return $ret;
}
# ----------------------------------------------------------------------------------==> . elim2_clen
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_clen($id_rodina,$id_orig,$id_copy) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori - vymazat => do _track napsat id_rodina.role
  $tvori= select1("CONCAT(id_rodina,'.',role)","tvori", "id_rodina=$id_rodina AND id_osoba=$id_copy");
  query("DELETE FROM tvori WHERE id_rodina=$id_rodina AND id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_oso=$id_copy");
  query("UPDATE platba SET id_oso=$id_orig WHERE id_oso=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= (int)$access_orig | (int)$access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $info= "access:$access_orig;xtvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'clen','d','$info',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");
end:
  return $ret;
}
# --------------------------------------------------------------------------------==> . elim2_rodina
# ASK
# zamění všechny výskyty kopie za originál v POBYT, TVORI, DAR, PLATBA a kopii smaže
function elim2_rodina($id_orig,$id_copy) {
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  if ( $id_orig!=$id_copy ) {
    $now= date("Y-m-d H:i:s");
    // pobyt
    $pobyt= select("GROUP_CONCAT(id_pobyt)","pobyt", "i0_rodina=$id_copy");
    query("UPDATE pobyt  SET i0_rodina=$id_orig WHERE i0_rodina=$id_copy");
    // tvori
    $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_rodina=$id_copy");
    query("UPDATE tvori  SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // dar
    $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_rodina=$id_copy");
    query("UPDATE dar    SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
//    // platba ... stará verze tabulky
//    $platba= select("GROUP_CONCAT(id_platba)","platba","id_rodina=$id_copy");
//    query("UPDATE platba SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // smazání kopie
    query("UPDATE rodina SET deleted='D rodina=$id_orig' WHERE id_rodina=$id_copy");
    // opravy v originálu
    $access_orig= select("access","rodina","id_rodina=$id_orig");
    $access_copy= select("access","rodina","id_rodina=$id_copy");
    $access= (int)$access_orig | (int)$access_copy;
    query("UPDATE rodina SET access=$access WHERE id_rodina=$id_orig");
    // zápis o ztotožnění rodin do _track jako op=d (duplicita)
    $info= "access:$access_orig;pobyt:$pobyt;tvori:$tvori;dar:$dar;platba:$platba";
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_orig,'','d','$info',$id_copy)");
    // zápis o smazání kopie do _track jako op=x (eXtract)
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_copy,'rodina','x','smazaná kopie',$id_orig)");
  }
  // odstranění duplicit v tabulce TVORI
  $qt= pdo_qry("
    SELECT COUNT(*) AS _n,GROUP_CONCAT(id_tvori ORDER BY id_tvori) AS _ids FROM tvori
    WHERE id_rodina=$id_orig GROUP BY id_osoba,role HAVING _n>1");
  while (($t= pdo_fetch_object($qt))) {
    $idts= explode(',',$t->_ids);
    for ($i= 1; $i<count($idts); $i++) {
      query("DELETE FROM tvori WHERE id_tvori={$idts[$i]}");
    }
  }
end:
  return $ret;
}
# ----------------------------------------------------------------------------- elim2_recovery_osoba
# obnoví smazanou osobu se záznamem v _track
function elim2_recovery_osoba($ido) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','osoba',"id_osoba=$ido");
  if ( $deleted ) {
    // obnovení
    query("UPDATE osoba SET deleted='' WHERE id_osoba=$ido");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','osoba',$ido,'','r','','$deleted')");
  }
  return $deleted;
}
# ---------------------------------------------------------------------------- elim2_recovery_rodina
# obnoví smazanou rodinu se záznamem v _track
function elim2_recovery_rodina($idr) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','rodina',"id_rodina=$idr");
  if ( $deleted ) {
    // obnovení
    query("UPDATE rodina SET deleted='' WHERE id_rodina=$idr");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','rodina',$idr,'','r','','$deleted')");
  }
  return $deleted;
}
# --------------------------------------------------------------------------------- elim2_data_osoba
# načte data OSOBA+TVORI včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_osoba($ido,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= pdo_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido $AND_kdy
  ");
  while (($z= pdo_fetch_object($zs))) {
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
  $os= pdo_qry("
    SELECT MAX(CONCAT(datum_od,':',a.nazev)) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= pdo_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->last_akce= $o->_last;
  // zjištění kmenové rodiny
  $kmen= ''; $idk= 0;
  $rs= pdo_qry("
    SELECT id_rodina,role,nazev
    FROM osoba AS o
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_osoba=$ido
  ");
  while (($r= pdo_fetch_object($rs))) {
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
# -------------------------------------------------------------------------------- elim2_data_rodina
# načte data RODINA včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_rodina($idr,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= pdo_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr $AND_kdy
  ");
  while (($z= pdo_fetch_object($zs))) {
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
  $os= pdo_qry("
    SELECT r.*, MAX(CONCAT(datum_od,': ',a.nazev)) AS _last
    FROM rodina AS r
    LEFT JOIN tvori AS t USING (id_rodina)
    LEFT JOIN spolu AS s USING (id_osoba)
    LEFT JOIN pobyt AS p USING (id_pobyt)
    LEFT JOIN akce AS a ON id_akce=id_duakce
    WHERE id_rodina=$idr
    GROUP BY id_rodina
  ");
  $o= pdo_fetch_object($os);
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
/** =========================================================================================> MAIL2 **/
# =========================================================================================> . vzory
# ------------------------------------------------------------------------------------- mail2 footer
function mail2_footer($op,$access,$access_name,$idu,$change='') { trace();
//  global $json;
  $ans= '';
  $org= $access_name->$access;
  $s_options= select("options","_user","id_user='$idu'",'ezer_system');
  $options= json_decode($s_options);
  switch ($op) {
  case 'show':
    $ans= is_array($options->email_foot) && isset($options->email_foot[$access])
        ? $options->email_foot[$access] 
        : "<i>patička pro $org nebyla ještě vyplněna</i>";
    break;
  case 'load':
    $ans= is_array($options->email_foot) && isset($options->email_foot[$access])
        ? $options->email_foot[$access] 
        : "";
    break;
  case 'save':
    if ( !is_array($options->email_foot) ) {
      $options->email_foot= array("","","","","");
    }
    $options->email_foot[$access]= $change;
    $options_s= ezer_json_encode($options);
    query("UPDATE _user SET options='$options_s' WHERE id_user='$idu'",'ezer_system');
    break;
  }
  return $ans;
}
# --------------------------------------------------------------------------------- mail2 vzor_pobyt
# pošle mail daného typu účastníkovi pobytu - zatím typ=potvrzeni_platby
#                                                                         !!! + platba souběžné akce
function mail2_vzor_pobyt2($id_pobyt,$typ,$u_poradi,$from,$vyrizuje,$varianta,$poslat=0) {
//  global $ezer_root;
  $ret= (object)array();

  // načtení a kontrola pobytu + mail + nazev akce
  $p= (object)array();
  $rm= pdo_qry("
    SELECT id_uhrada,
     IFNULL(u_castka,0),u_datum,u_stav,
     GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')),IFNULL(GROUP_CONCAT(DISTINCT r.emaily),''),
     a.nazev,a.access,a.hnizda,p.hnizdo
    FROM pobyt AS p
    LEFT JOIN uhrada AS u USING (id_pobyt)
    JOIN akce AS a ON p.id_akce=a.id_duakce
    LEFT JOIN akce AS x ON x.id_hlavni=a.id_duakce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,1)
    LEFT JOIN rodina AS r USING (id_rodina)
    WHERE id_pobyt=$id_pobyt AND u_poradi=$u_poradi AND IFNULL(t.role IN ('a','b'),true)
  ");
  list($id_uhrada,$castka,$dne,$potvrzeno,
    $omaily,$rmaily,$p->platba_akce,$access,$hnizda,$hnizdo)= pdo_fetch_row($rm);
  if ( !$id_uhrada ) { $ret->err= "CHYBA platba č.$u_poradi neexistuje"; goto end; }
  if ( !$castka ) { $ret->err= "CHYBA: není zapsána částka"; goto end; }
  if ( $dne=='0000-00-00' ) { $ret->err= "CHYBA: není zapsáno datum platby"; goto end; }
  if ( $castka && $potvrzeno==3 ) { $ret->err= "CHYBA: platba již byla potvrzena"; goto end; }
//  }

  // naplnění proměnných mailu
  $p->platba_den= sql_date1($dne);
  $p->platba_castka= number_format($castka, 0, '.', '&nbsp;')."&nbsp;Kč";
  $p->vyrizuje= $vyrizuje;
  // doplnění názvu hnízda, má-li smysl
  if ($hnizda && $hnizdo) {
    $hnizda= explode(',',$hnizda);
    if (isset($hnizda[$hnizdo-1])) {
      $p->platba_akce.= ", hnízdo {$hnizda[$hnizdo-1]}";
    }
  }
//  list($nazev,$obsah,$vars)=
//     select('nazev,obsah,var_list','dopis',"typ='potvrzeni_platby' AND access=$access");

  // načtení vzoru dopisu - verze podle $varianta
  $vzor= array();
  $rv= pdo_qry("SELECT nazev,obsah,var_list FROM dopis WHERE typ='potvrzeni_platby' AND access=$access");
  while ($rv && ($v= pdo_fetch_row($rv))) {
    $vzor[]= $v;  
  } 
//  debug($vzor);
  if (!count($vzor)) { $ret->err= "CHYBA: nebyl nalezen vzor dopisu 'potvrzeni_platby' "; goto end; }
  else if ($varianta>count($vzor)) $varianta= 1; // cyklické opakování
  $ret->next= $varianta+1;
  list($nazev,$obsah,$vars)= $vzor[$varianta-1];
  // personifikace
  foreach ( explode(',',$vars) as $var ) {
    $var= trim($var);
    $obsah= str_replace('{'.$var.'}',$p->$var,$obsah);
  }
  // extrakce adresy
  $maily= trim(str_replace(';',',',"$omaily,$rmaily")," ,");
                                                              display("maily=$maily");
  if ( !$maily ) { $ret->err= "CHYBA účastníci nemají uvedené maily"; goto end; }
  $report= "<hr>Od:$vyrizuje &lt;$from&gt;<br>Komu:$maily<br>Předmět:$nazev<hr>$obsah";

  if ( $poslat ) {
    // poslání mailu - při úspěchu zápis o potvrzení
    $mail= mail2_new_PHPMailer();
    if ( !$mail ) { 
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $ret->err= "CHYBA při odesílání mailu z '$ze' došlo k chybě: odesílací adresa nelze použít (SMTP)";
      goto end;
    }
    // test odesílací adresy -- pro maily pod seznam.cz musí být stejná jako přihlašovací
    $mail->From= preg_match("/@chlapi.cz|@seznam.cz/",$mail->Username) ? $mail->Username : $from;
    $mail->AddReplyTo($from);
    $mail->FromName= $vyrizuje;
    foreach(preg_split("/,\s*|;\s*|\s+/",trim($maily," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
      $mail->AddAddress($adresa);   // pošli na 1. adresu
    }
    $mail->Subject= $nazev;
    $mail->Body= $obsah;
    $ok= $mail->Send();
    if ( $ok  ) {
      // zápis o potvrzení
      $ret->msg= "Byl odeslán mail$report";
      query("UPDATE uhrada SET u_stav=3 WHERE id_uhrada=$id_uhrada");
    }
    else {
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $ret->err= "CHYBA při odesílání mailu z '$ze' došlo k chybě: $mail->ErrorInfo";
      goto end;
    }
  }
  else {
    $ret->msg= "Je připraven mail - mám ho poslat?$report";
    $ret->butt= "poslat:send,neposílat:quit,jiný dopis:next";
  }
end:
  return $ret;
}
//function mail2_vzor_pobyt($id_pobyt,$typ,$from,$vyrizuje,$poslat=0) {
//  global $ezer_root;
//  $ret= (object)array();
//
//  // načtení a kontrola pobytu + mail + nazev akce
//  $p= (object)array();
//  $rm= pdo_qry("
//    SELECT IFNULL(x.id_duakce,0),
//     -- p.platba,p.datplatby,p.potvrzeno,
//     -- p.platba_d,p.datplatby_d,p.potvrzeno_d,
//     GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')),IFNULL(GROUP_CONCAT(DISTINCT r.emaily),''),
//     a.nazev,a.access,a.hnizda,p.hnizdo
//    FROM pobyt AS p
//    JOIN akce AS a ON p.id_akce=a.id_duakce
//    LEFT JOIN akce AS x ON x.id_hlavni=a.id_duakce
//    JOIN spolu AS s USING(id_pobyt)
//    JOIN osoba AS o ON s.id_osoba=o.id_osoba
//    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,1)
//    LEFT JOIN rodina AS r USING (id_rodina)
//    WHERE id_pobyt=$id_pobyt
//  ");
//  if (!$rm ) { $ret->err= "CHYBA záznam nenalezen"; goto end; }
//  list($soubezna,$castka,$dne,$potvrzeno,$castka_d,$dne_d,$potvrzeno_d,
//    $omaily,$rmaily,$p->platba_akce,$access,$hnizda,$hnizdo)= pdo_fetch_row($rm);
//  if ( !$castka && !$castka_d ) {
//    $ret->err= "CHYBA: ještě nebylo nic zaplaceno"; goto end; }
//  if ( $castka && $dne=='0000-00-00' || $castka_d && $dne_d=='0000-00-00' ) {
//    $ret->err= "CHYBA: není zapsáno datum platby"; goto end; }
//  if ( $soubezna  ) {
//    if ( $potvrzeno && $potvrzeno_d ) {
//      $ret->err= "CHYBA: obě platby již byly potvrzeny"; goto end;
//    }
//    if ( $castka && $potvrzeno && $castka_d && $potvrzeno_d ) {
//      $ret->err= "CHYBA: platba již byla potvrzena"; goto end;
//    }
//  }
//  else {
//    if ( $castka && $potvrzeno ) { $ret->err= "CHYBA: platba již byla potvrzena"; goto end; }
//  }
//
//  // naplnění proměnných mailu
//  $p->platba_den= sql_date1($castka && !$potvrzeno ? $dne : $dne_d);
//  $p->platba_castka=
//    number_format($castka && !$potvrzeno ? $castka : $castka_d, 0, '.', '&nbsp;')."&nbsp;Kč";
//  $p->vyrizuje= $vyrizuje;
//  // doplnění názvu hnízda, má-li smysl
//  if ($hnizda && $hnizdo) {
//    $hnizda= explode(',',$hnizda);
//    if (isset($hnizda[$hnizdo-1])) {
//      $p->platba_akce.= ", hnízdo {$hnizda[$hnizdo-1]}";
//    }
//  }
//
//  // načtení vzoru dopisu
//  list($nazev,$obsah,$vars)=
//    select('nazev,obsah,var_list','dopis',"typ='potvrzeni_platby' AND access=$access");
//
//  // personifikace
//  foreach ( explode(',',$vars) as $var ) {
//    $var= trim($var);
//    $obsah= str_replace('{'.$var.'}',$p->$var,$obsah);
//  }
//  // extrakce adresy
//  $maily= trim(str_replace(';',',',"$omaily,$rmaily")," ,");
//                                                              display("maily=$maily");
//  if ( !$maily ) { $ret->err= "CHYBA účastníci nemají uvedené maily"; goto end; }
//  $report= "<hr>Od:$vyrizuje &lt;$from&gt;<br>Komu:$maily<br>Předmět:$nazev<hr>$obsah";
//
//  if ( $poslat ) {
//    // poslání mailu - při úspěchu zápis o potvrzení
//    $mail= mail2_new_PHPMailer();
//    if ( !$mail ) { 
//      $ze= isset($mail->Username) ? $mail->Username : '?';
//      $ret->err= "CHYBA při odesílání mailu z '$ze' došlo k chybě: odesílací adresa nelze použít (SMTP)";
//      goto end;
//    }
//    // proměnné údaje
//    $mail->From= $from;
//    $mail->AddReplyTo($from);
//    $mail->FromName= $vyrizuje;
//    foreach(preg_split("/,\s*|;\s*|\s+/",trim($maily," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
//      $mail->AddAddress($adresa);   // pošli na 1. adresu
//    }
//    $mail->Subject= $nazev;
//    $mail->Body= $obsah;
//    $ok= $mail->Send();
//    if ( $ok  ) {
//      // zápis o potvrzení
//      $ret->msg= "Byl odeslán mail$report";
//      $field= $castka && !$potvrzeno ? 'potvrzeno' : 'potvrzeno_d';
//      query("UPDATE pobyt SET $field=1 WHERE id_pobyt=$id_pobyt");
//    }
//    else {
//      $ze= isset($mail->Username) ? $mail->Username : '?';
//      $ret->err= "CHYBA při odesílání mailu z '$ze' došlo k chybě: $mail->ErrorInfo";
//      goto end;
//    }
//  }
//  else {
//    $ret->msg= "Je připraven mail - mám ho poslat?$report";
//  }
//end:
//  return $ret;
//}
# =======================================================================================> . mailist
# ---------------------------------------------------------------------------------- mail2 lst_using
# vrátí informaci o použití mailistu
function mail2_lst_using($id_mailist) {
  $dopisy= $poslane= $neposlane= $err= 0;
  $rs= pdo_qry("
    SELECT COUNT(DISTINCT id_dopis) AS _dopisy,SUM(IF(m.stav=4,1,0)) AS _poslane,
      IF(sexpr LIKE '%access%',0,1) AS _ok
    FROM mailist AS l
    LEFT JOIN dopis AS d USING (id_mailist)
    LEFT JOIN mail AS m USING (id_dopis)
    WHERE id_mailist=$id_mailist
    GROUP BY id_dopis");
  while ($rs && ($s= pdo_fetch_object($rs))) {
    $dopisy= $s->_dopisy;
    $poslane+= $s->_poslane ? 1 : 0;
    $neposlane+= $s->_poslane ? 0 : 1;
    $err+= $s->_ok;
  }
  $html= "Použití: v $dopisy dopisech, z toho <br>$poslane rozeslané a $neposlane nerozeslané";
  $html.= $err ? "<br><br><span style='background-color:yellow'>POZOR - nutno znovu uložit</span>" : '';
  return $html;
}
# ------------------------------------------------------------------------------------ mail2 mailist
# vrátí daný mailist ve tvaru pro selects
# pokud je uvedeno par.typ='o' pro osoby, 'r' pro rodiny
function mail2_mailist($access,$par=null) {
  $sel= '';
  $AND=  $par && $par->komu ? "AND komu='{$par->komu}'" : '';
  $AND.= $par && $par->ucel ? "AND ucel LIKE '{$par->ucel}'" : '';
  $mr= pdo_qry("SELECT id_mailist,ucel,access FROM mailist WHERE access&$access $AND 
    ORDER BY UPPER(ucel)");
  while ($mr && ($m= pdo_fetch_object($mr))) {
    $a= $m->access;
//    $css= $a==1 ? 'ezer_ys' : ($a==2 ? 'ezer_fa' : ($a==3 ? 'ezer_db' : ''));
    $css= ($a&1 && $a&2) ? 'ezer_db' : ($a&2 ? 'ezer_fa' : ($a&1 ? 'ezer_ys' : ''));
    if ($a&64) $css.= " ezer_ds";
    $sel.= ",{$m->ucel}:{$m->id_mailist}:$css";
  }
  display("SELECTS:$sel");
  return $sel ? substr($sel,1) : '';
}
# --------------------------------------------------------------------------------- mail2 lst_delete
# ASK
# zjisté možnost smazání mailistu (to_delete=0) tzn. že na něj není vázán žádný dopis
# a pro to_delete=1 jej smaže
function mail2_lst_delete($id_mailist,$to_delete=0) {
  $ret= (object)array('ok'=>0,'msg'=>'');
  if ( !$to_delete ) {
    if ( $id_mailist<=3 ) {
      $ret->msg= "testovací mail-listy nelze smazat";
    }
    else {
      $n= select('COUNT(*)','mailist JOIN dopis USING (id_mailist)',"id_mailist=$id_mailist");
      if ( $n )
        $ret->msg= "nelze smazat, je použit v $n dopisech
          <br>(nejprve je třeba smazat všechny dopisy)";
      else
        $ret->ok= 1;
    }
  }
  else {
    $ret->ok= query("DELETE FROM mailist WHERE id_mailist=$id_mailist");
  }
  return $ret;
}
# --------------------------------------------------------------------------------- mail2 lst_access
# vrátí údaje daného maillistu (ZRUŠENO: s provedenou substitucí podle access uživatele)
function mail2_lst_access($id_mailist) {  trace();
  global $USER;                                         // debug($USER);
  $ml= select_object('*','mailist',"id_mailist=$id_mailist");
//   if ( !strpos($ml->sexpr,'[HAVING_ACCESS]') ) {
//     fce_warning("dotaz zatím není uzpůsoben pro obě databáze - stačí jej znovu uložit");
//     $ml->warning= 1;
//     goto end;
//   }
  $ml->sexpr= str_replace('&lt;','<',str_replace('&gt;','>',$ml->sexpr));
//   // doplnění práv uživatele
//   $ml->sexpr= str_replace('[HAVING_ACCESS]',"HAVING o.access&{$USER->access}",$ml->sexpr);
end:
  return $ml;
}
# --------------------------------------------------------------------------- mail2 lst_confirm_spec
# spočítá maily podle daného maillistu
function mail2_lst_confirm_spec($id_mailist,$id_dopis) {  trace();
  $ret= (object)array('specialni'=>0, 'prepsat'=>'', 'pocet'=>'');
  // speciální?
  list($ret->specialni,$qry)= select('specialni,sexpr','mailist',"id_mailist=$id_mailist");
  if ( !$ret->specialni ) goto end;
  // jsou už vygenerované maily
  $ret->prepsat= select('COUNT(*)','mail',"id_dopis=$id_dopis")
    ? "Opravdu přepsat předchozí maily?" : '';
  // počet nově vygenerovaných
  $res= pdo_qry($qry);
  $ret->pocet= "Opravdu vygenerovat maily na ".pdo_num_rows($res)." adres?";
end:
  return $ret;
}
# ----------------------------------------------------------------------------- mail2 lst_regen_spec
# přegeneruje 1 daný mail s nastaveným specialni a parms
# pokud je definováno corr přepíše proměnné v dopise
# corr= {darce}
function mail2_lst_regen_spec($id_dopis,$id_mail,$id_osoba,$corr=null) {  debug($corr,"mail2_lst_regen_spec");
  $ret= (object)array('msg'=>'','err'=>'');
  $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
  $ml= mail2_lst_access($id_mailist);
  $parms= json_decode($ml->parms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    $rok= date('Y')+$parms->rok;
    $qry= $ml->sexpr;
    $qry= str_replace("GROUP BY","AND id_osoba=$id_osoba GROUP BY",$qry);
    $os= pdo_qry($qry);
    if ( !$os ) {  $ret->msg= "přegenerování se nepovedlo"; goto end; }
    $o= pdo_fetch_object($os);
    // přegeneruj PDF s potvrzením do $x->path
    if ($corr) {
      // uplatni korekci, pokud je definována 
      $o->sex= $corr->sex;
      $o->jmeno= $corr->jmeno;
      $o->prijmeni= $corr->prijmeni;
      $o->_ulice= $corr->_ulice;
      $o->_psc= $corr->_psc;
      $o->_obec= $corr->_obec;
    }
    $x= mail2_mai_potvr("Pf",$o,$rok);
    // oprav mail
    $rs= pdo_qry("UPDATE mail SET stav=3,email='{$o->_email}' WHERE id_mail=$id_mail");
    $num+= pdo_affected_rows($rs);
    // informační zpráva
    $ret->msg= "Mail pro {$o->_name} včetně potvrzení {$x->fname} byl přegenerován";
    break;
  default:
    fce_error("není implementováno");
  }
end:
//                                                         debug($ret,"mail2_lst_posli_spec end");
  return $ret;
}
# ----------------------------------------------------------------------------- mail2 lst_posli_spec
# PROCESS
# vygeneruje sadu mailů podle daného maillistu s nastaveným specialni a parms
# davka = {todo,done,step,msg,error,mails} pokud done=0 jde o zahájení procesu
function mail2_lst_posli_spec($davka) {  trace();
  global $ezer_path_docs;
  $davka->msg= '';
  $id_mailist= select('id_mailist','dopis',"id_dopis={$davka->dopis}");
  $ml= mail2_lst_access($id_mailist);
  $parms= json_decode($ml->parms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    $rok= date('Y')+$parms->rok;
    if ($davka->todo==0) { // --------------------------------- zahájení
      // smaž starý seznam a stará potvrzení
      pdo_qry("DELETE FROM mail WHERE id_dopis={$davka->dopis}");
      if ( is_dir("$ezer_path_docs/db2") ) {
        $files= glob("$ezer_path_docs/db2/potvrzeni_{$rok}*.pdf");
        foreach ($files as $file) {
          unlink($file);
        }
      }
      // a zjisti celkový počet
      $davka->done= 0;
      $davka->error= '';
      $os= pdo_qry($ml->sexpr);
      while ($os && ($o= pdo_fetch_object($os))) {
        $davka->todo++;
      }
      if (!$davka->todo) { $davka->msg= "nejsou žádná potrzení"; break; }
      // rychle skončíme, aby se nastavil termometr
    }
    else { // ------------------------------------------------- generování STEP potvrzení
      $step= 0;
      // projdi všechny relevantní dárce podle dotazu z maillistu
      // a vytvoř davka.step ještě nevytvořených mailů
      $os= pdo_qry($ml->sexpr);
      while ($os && ($o= pdo_fetch_object($os))) {
        // pokud již má mail, přeskoč ho
        $ma= select('id_mail','mail',"id_dopis={$davka->dopis} AND id_clen={$o->_id}");
        if ($ma) continue;
        // pokud nema, pokračujeme 
        $email= $o->_email ?: '*';
        // vygeneruj PDF s potvrzením do $x->path
        $x= mail2_mai_potvr("Pf",$o,$rok);
        $pdf= $x->fname;
        // vlož mail
        query("INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email,priloha)
               VALUE (1,'@',0,{$davka->dopis},{$o->_id},'$email','$pdf')");
        if ( preg_match("/[*]/",$email) ) 
          $davka->msg.= " {$o->jmeno} {$o->prijmeni} nemá mail ";
        $step++;
        $davka->done++;
        // pokud jsme neprošli davka.step osob, pokračujeme 
        if ($step >= $davka->step) {
          break;
        }
      }
      if ($davka->done>=$davka->todo) {
        // oprav počet v DOPIS
        query("UPDATE dopis SET pocet={$davka->done} WHERE id_dopis={$davka->dopis}");
        // informační zpráva
        $davka->msg= "Bylo vygenerováno {$davka->done} mailů {$davka->msg}";
      }
    }
    break;
  default:
    fce_error("není implemntováno");
  }
//                                                         debug($ret,"mail2_lst_posli_spec end");
  return $davka;
}
# ----------------------------------------------------------------------------------- mail2 lst_read
# převod parm do objektu
function mail2_lst_read($parm) { //trace();
//  global $json;
  $obj= json_decode($parm);
  $obj= isset($obj->ano_akce) ? $obj : 0;
//                                                 debug($obj,"mail2_lst_read($parm)");
  return $obj;
}
# --------------------------------------------------------------------------------------- mail2 mapa
# ASK
# získání seznamu souřadnic bydlišť adresátů mailistu
function mail2_mapa($id_mailist) {  trace();
  $psc= $obec= array();
  // dotaz
  $gq= select("sexpr","mailist","id_mailist=$id_mailist");
//                                         display($gq);
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
  $gr= @pdo_qry($gq);
  if ( !$gr ) {
    $html= pdo_error()."<hr>".nl2br($gq);
    goto end;
  }
  else while ( $gr && ($g= pdo_fetch_object($gr)) ) {
    // najdeme použitá PSČ
    $p= $g->_psc;
    list($prijmeni,$jmeno)= explode(' ',$g->_name);
    $psc[$p].= "$prijmeni ";
    $obec[$p]= $obec[$p] ?: $g->_obec;
  }
//                                         debug($psc);
end:
  return mapa2_psc($psc,$obec); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# --------------------------------------------------------------------------------- mail2 mai_export
# vygeneruje tabulku adresátů (id_osoba, prijmeni jmeno, email)) pro Excel a vrátí na ni odkaz
function mail2_mai_export($idd) {  trace();
  $tab= (object)array(
      'html'=>'',
      'tits' => array('příjmení:15','jméno:10','email:30','id:6'),
      'flds' => array('prijmeni','jmeno','email','id'),
      'clmn' => array()
      );
  $nazev= '';
  $rs= pdo_qry("
    SELECT id_clen,prijmeni,jmeno,m.email,d.nazev
    FROM mail AS m
    JOIN dopis AS d USING (id_dopis)
    JOIN osoba AS o ON o.id_osoba=m.id_clen
    WHERE id_dopis=$idd AND stav=4
    ORDER BY prijmeni,jmeno
    ");
  while ( $rs && (list($idc,$prijmeni,$jmeno,$email,$n)= pdo_fetch_array($rs)) ) {
    if ( !$nazev ) $nazev= $n;
    $tab->clmn[]= array('prijmeni'=>$prijmeni,'jmeno'=>$jmeno,'email'=>$email,'id'=>$idc);
  }
  $ret= sta2_excel_export("Seznam adresátů mailu $nazev",$tab);
  return $ret->html;
}
# ------------------------------------------------------------------------------------ mail2 lst_try
# mode=0 -- spustit a ukázat dotaz a také výsledek
# mode=1 -- zobrazit argument jako html
function mail2_lst_try($gq,$mode=0) { trace();
//   global $USER;                                         // debug($USER);
//   $access= $USER->access;
  $html= $del= '';
  if ( !$gq ) {
    $html= "mail-list nebyl uložen";
    goto end;
  }
  switch ($mode) {
  case 0:
    $n= $nw= $nm= $nx= $nu= $nr= 0;
    $gq= str_replace('&gt;','>',$gq);
    $gq= str_replace('&lt;','<',$gq);
    // ZRUŠENO: doplnění práv uživatele
    // $gq= str_replace('[HAVING_ACCESS]',"HAVING o.access&$access",$gq);
    $gr= @pdo_qry($gq);
    if ( !$gr ) {
      $html= pdo_error()."<hr>".nl2br($gq);
      goto end;
    }
    else while ( $gr && ($g= pdo_fetch_object($gr)) ) {
      $n++;
      $name= str_replace(' ','&nbsp;',$g->_name);
      if ( !$g->_email ) {
        $nw++;
        $name= "<span style='color:darkred'>$name</span>";
      }
      if ( $g->_umrti ) {
        $nu++;
        $name= "<span style='background:silver'>+ $name</span>";
      }
      if ( $g->rozvod ) {
        $nr++;
        $name= "<span style='background:yellow'>x $name</span>";
      }
      if ( $g->nomail ) {
        $nm++;
        $name= "<span style='background-color:orange'>$name</span>";
      }
      if ( $g->_email[0]=='*' ) {
        // vyřazený mail
        $nx++;
        $name= "<strike><b>$name</b></strike>";
      }
      $html.= "$del$name";
      $del= ', ';
    }
    $warn= $nw+$nm+$nx+$nu+$nr ? " (" : '';
    $warn.= $nw ? "$nw <span style='color:darkred'>nemá email</span> ani rodinný" : '';
    $warn.= $nw && $nm ? ", " : '';
    $warn.= $nm ? "$nm <span style='background-color:yellow'>nechce hromadné</span> informace
      - budou vyňati z mail-listu" : '';
    $warn.= ($nw||$nm) && $nx ? ", " : '';
    $warn.= $nx ? "$nx má <strike>zneplatněný email</strike>" : '';
    $warn.= ($nw||$nm||$nx) && $nu ? ", " : '';
    $warn.= $nu ? "$nu  <strike>zemřelo</strike>" : '';
    $warn.= ($nw||$nm||$nx||$nu) && $nr ? ", " : '';
    $warn.= $nr ? "$nr  <strike>rozvedeno</strike>" : '';
    $warn.= $nw+$nm+$nx+$nu+$nr ? ")" : '';
    $html= "<b>Nalezeno $n adresátů$warn:</b><br>$html";
    break;
  case 1:
    $html= nl2br($gq);
    break;
  }
end:
  return $html;
}
# ========================================================================================> . emaily
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce mail2_mai_qry
# ---------------------------------------------------------------------------------- mail2 mai_potvr
# vygeneruje PDF s daňovým potvrzením s výsledkem
# ret->fname - jméno vygenerovaného PDF souboru
# ret->href  - odkaz na soubor
# ret->fpath - úplná lokální cesta k souboru
# ret->log   - log
function mail2_mai_potvr($druh,$o,$rok) {  //trace();
  $ret= (object)array('msg'=>'');
  // report
  $d= select("*","dopis","typ='$druh'");
  $vzor= $d->obsah;
  $sablona= $d->sablona;
  $texty= array();
  $parss= array();
  // výpočet proměnných použitých v dopisu
  $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$vzor,$list);
  $vars= array_merge($list[1],array('vyrizeno'));
//                                                         debug($vars,"vars");
  $dary= json_decode($o->pars);
  $data= $dary->data;
  $castka= number_format($o->castka, 0, '.', ' ');
  $id_osoba= $o->_id;
                                        display("mail2_mai_potvr $id_osoba {$o->castka}");
  $prijmeni= $o->prijmeni;
  $jmeno= $o->jmeno;
  $sex= $o->sex;
  $ulice= $o->_ulice;
  $psc= $o->_psc;
  $obec= $o->_obec;
  $osloveni= $sex==1 ? "pan" : ($sex==2 ? "paní" : "");
  $Osloveni= $sex==1 ? "Pan" : ($sex==2 ? "Paní" : "");
  $adr= "$osloveni,$prijmeni,$jmeno,$sex,$ulice,$psc,$obec";
  $html= "<table>";
  $html.= "<tr><td>$id</td><td>$castka</td><td>$adr</td><td>$data</td></tr>";
  // definice parametrů pro potvrzující dopis
  $parss[$n]= (object)array();
  $parss[$n]->dar_datum= $data;
  $parss[$n]->dar_castka= str_replace(' ','&nbsp;',$castka);
  $parss[$n]->darce= "$osloveni <b>$jmeno $prijmeni</b>";
  $parss[$n]->darce_a= $sex==2 ? "a" : "";
  $parss[$n]->vyrizeno= date('j. n. Y');
  // substituce v 'text' a 'odeslano'
  $text= $vzor;
  $odeslano= select('obsah','dopis_cast',"name='odeslano'");
  if ( $is_vars ) foreach ($vars as $var ) {
    $text= str_replace('{'.$var.'}',$parss[$n]->$var,$text);
    $odeslano= str_replace('{'.$var.'}',$parss[$n]->$var,$odeslano);
  }
  // úprava lámání textu kolem jednopísmenných předložek a přilepení Kč k částce
  $text= preg_replace(array('/ ([v|k|z|s|a|o|u|i]) /u','/ Kč/u'),array(' \1&nbsp;','&nbsp;Kč'),$text);
  $texty[$n]= (object)array();
  $texty[$n]->adresa= "<b>$Osloveni<br>$jmeno $prijmeni<br>$ulice<br>$psc $obec</b>";
  $texty[$n]->odeslano= $odeslano;
  $texty[$n]->text= $text;
  $n++;
  $html.= "<hr>$text";
  $html.= "</table>";
  // předání k tisku
//                                                 debug($parss);
//                                                 display($html);
  global $ezer_path_docs, $ezer_root;
  $ret->fname= "potvrzeni_{$rok}_$id_osoba.pdf";
  $ret->fpath= "$ezer_path_docs/$ezer_root/{$ret->fname}";
  $dlouhe= tc_dopisy($texty,$ret->fpath,'','_user',$listu);
  $ret->href= "<a href='docs/$ezer_root/{$ret->fname}' target='pdf'>{$ret->fname}</a>";
//   $html.= " Bylo vygenerováno $listu potvrzení do $href.";
  // konec
  $ret->log= $html;
  return $ret;
}
# ----------------------------------------------------------------------------------- mail2 mai_text
# přečtení mailu
# pokud je $akce=1 vrátí název a rok akce nebo název mailistu
function mail2_mai_text($id_dopis,$akce=0) {  //trace();
  $ret= (object)array('html'=>'');
  $d= null;
  $prilohy= $obsah= '';
  $elem= !$akce ? '' 
      : ",CASE "
        . "WHEN id_duakce!=0 THEN CONCAT(a.nazev,', ',YEAR(datum_od)) "
        . "WHEN id_mailist!=0 THEN m.ucel "
        . "WHEN cis_skupina!=0 THEN c.zkratka "
        . "ELSE '???' END AS _adrs,"
      . "COUNT(*) AS _pocet";
  $join= !$akce ? ''
      : "LEFT JOIN akce AS a USING (id_duakce) "
      . "LEFT JOIN mailist AS m USING (id_mailist) "
      . "LEFT JOIN mail USING (id_dopis)"
      . "LEFT JOIN _cis AS c ON data=cis_skupina AND c.druh='db_maily_sql'";
  $group= !$akce ? ''
      : "GROUP BY id_dopis";
  try {
    $qry= "SELECT d.nazev,obsah,prilohy,id_duakce,id_mailist,cis_skupina $elem "
        . "FROM dopis AS d $join WHERE id_dopis=$id_dopis $group";
    $res= pdo_qry($qry);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { 
    display($e); fce_error("mail2_mai_text: průběžný dopis No.'$id_dopis' nebyl nalezen"); 
  }
  $predmet= $d->nazev;
  $obsah= $d->obsah;
  // příloha?
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $priloha ) {
      if ( $akce ) {
        list($file)= explode(':',$priloha);
        $prilohy.= " <a target='docs' href='/docs/db2/$file'>$priloha</a>, ";
      }
      else {
        $prilohy.= "<hr/><b>Příloha:</b> $priloha";
        $typ= strtolower(substr($priloha,-4));
        if ( $typ=='.jpg' || $typ=='.gif' || $typ=='.png' ) {
          $prilohy.= "<img src='docs/$priloha' />";
        }
      }
    }
  }
  if ( $akce ) {
    $komu= $d->id_duakce ? "<b>AKCE:</b> $d->_adrs" : ( 
           $d->id_mailist ? "<b>MAILIST:</b> $d->_adrs" : (
           $d->cis_skupina ? "<b>SKUPINA:</b> $d->_adrs" : 
           $d->_adrs ));
    $pocet= "<hr><b>ADRESÁTŮ:</b> $d->_pocet";
    $prilohy= $prilohy ? "<hr><b>PŘÍLOHY:</b> $prilohy" : '';
    $ret->html= "$komu$pocet<hr><b>PŘEDMĚT:</b> $predmet$prilohy<hr>$obsah";
  }
  else {
    $ret->html= "<b>$predmet</b><hr>$obsah$prilohy";
  }
  return $ret;
}
# -------------------------------------------------------------------------------- mail2 mai_prazdny
# zjistí zda neexistuje starý seznam adresátů
function mail2_mai_prazdny($id_dopis) {  trace();
  $result= array('_error'=>0, '_prazdny'=> 1);
  // ověř prázdnost MAIL
  $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis";
  $res= pdo_qry($qry);
  if ( pdo_num_rows($res)>0 ) {
    $result['_html']= "Rozesílací seznam pro tento mail již existuje, stiskni ANO pokud má být přepsán novým";
    $result['_prazdny']= 0;
  }
  return $result;
}
# ------------------------------------------------------------------------------------ mail2 mai_qry
# sestaví SQL dotaz podle položky DOPIS.komu
# formát zápisu dotazu v číselníku:  A[|D[|cond]]
#   kde A je seznam aktivit oddělený čárkami
#   a D=1 pokud mají být začleněni pouze letošní a loňští dárci
#   a cond je obecná podmínka na položky tabulky CLEN
function mail2_mai_qry($komu) {  trace();
  list($aktivity,$is_dary,$cond)= explode('|',$komu);
  $and= $aktivity=='*' ? '' : "AND FIND_IN_SET(aktivita,'$aktivity')";
  if ( $cond ) $and.= " AND $cond";
  $letos= date('Y'); $loni= $letos-1;
  $qry= $is_dary
    ? "SELECT id_clen, email,
         BIT_OR(IF((YEAR(datum) BETWEEN $loni AND $letos) AND LEFT(dar.deleted,1)!='D'
           AND castka>0 AND akce='G',1,0)) AS is_darce
       FROM clen LEFT JOIN dar USING (id_clen)
       WHERE LEFT(clen.deleted,1)!='D' AND umrti=0 AND aktivita!=9 AND email!='' $and
       GROUP BY id_clen HAVING is_darce=1"
    : "SELECT id_clen, email FROM clen
       WHERE left(deleted,1)!='D' AND umrti=0 AND email!='' $and";
  return $qry;
}
# ---------------------------------------------------------------------------------- mail2 mai_omitt
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emailu z MAIL($id_dopis=$vynech)
# to je funkce určená k zamezení duplicit
function mail2_mai_omitt($id_dopis,$ids_vynech) {  trace();
  $msg= "Z mailů podle dopisu $id_dopis chci vynechat adresy z mailů podle dopisu $ids_vynech";
  // seznam vynechaných adres
  $vynech= array();
  $qv= "SELECT email FROM mail WHERE id_dopis IN ($ids_vynech) ";
  $rv= pdo_qry($qv);
  while ( $rv && ($v= pdo_fetch_object($rv)) ) {
    foreach(explode(',',str_replace(';','',str_replace(' ','',$v->email))) as $em) {
      $vynech[]= $em;
    }
  }
//                                                         debug($vynech,"vynechané adresy");
  $msg.= "<br>podezřelých je ".count($vynech)." adres";
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= pdo_qry($qd);
  while ( $rd && ($d= pdo_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='- $ids_vynech' WHERE id_mail={$d->id_mail} ";
        $ru= pdo_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n řádků";
  return $msg;
}
# --------------------------------------------------------------------------------- mail2 mai_omitt2
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
function mail2_mai_omitt2($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $vynech= explode(',',str_replace(' ','',$lst_vynech));
  $msg= "Z mailů podle dopisu $id_dopis chci vynechat ".count($vynech)." adres";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= pdo_qry($qd);
  while ( $rd && ($d= pdo_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
//                                         display("'$em'=".(in_array($em,$vynech)?1:0));
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='viz' WHERE id_mail={$d->id_mail} ";
        $ru= pdo_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# --------------------------------------------------------------------------------- mail2 mai_omitt3
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
# obsahující id_osoba adresátů
function mail2_mai_omitt3($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $lst_vynech= str_replace(' ','',$lst_vynech);
  $vynech= explode(',',$lst_vynech);
  $msg= "Ze seznamu adresátů dopisu $id_dopis chci vynechat ".count($vynech)." řádků";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $qu= "UPDATE mail SET stav=5,msg='dle ID' WHERE id_dopis=$id_dopis AND id_clen IN ($lst_vynech) ";
  $ru= pdo_qry($qu);
  $n= pdo_affected_rows($ru);
  $msg.= "<br>označeno bylo $n řádků";
  return $msg;
}
# -------------------------------------------------------------------------------- mail2 mai_doplnit
# zjistí počet adresátů pro doplnění rozesílání a sestaví dotaz pro confirm
# pokud $doplnit=1 tak přímo doplní tabulku mail
function mail2_mai_doplnit($id_dopis,$id_akce,$doplnit) {  trace();
  $ret= (object)array('err'=>1, 'html'=>'?');
  // zjistíme dopis.komu= 0:všem, 1:VPS..., 2:dlužníci, 3:OP
  list($komu,$obsah)= select('komu,obsah','dopis',"id_dopis=$id_dopis AND id_duakce=$id_akce");
  // zjistíme počet - POZOR KOPIE KÓDU SQL z mail2_mai_pocet
  $AND= $komu==0 ? " AND p.funkce IN (0,1,2,5)" : (
        $komu==1 ? " AND p.funkce IN (1,2,5)"   : (
        $komu==2 ? " AND p.funkce IN (0,1,2,5) " : (
        $komu==3 ?
           " AND IF(IFNULL(role,'a') IN ('a','b'),REPLACE(o.obcanka,' ','') NOT RLIKE '^[0-9]{9}$',0)"
       : " --- chybné komu --- " )));
  $HAVING= $komu==2 ? "HAVING _uhrada<_poplatek" : "";
  // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
  // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech, mají-li osobní
  $n_neobeslani= $n_novi= $n_pridano= $n_err= 0; $err= $dele= '';
  $x_pm= array(); // pole mailů pro daný pobyt
  $x_om= array(); // pole platných mailů dané osoby
  $o_jm= array(); // jména osob
  $x_po= array(); // osoby daného pobytu
  $rr= pdo_qry("
    SELECT s.id_osoba,id_pobyt,
    --  a.nazev,pouze,
      COUNT(*) AS _na_akci,
    --  avizo,
    --  GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
      GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno)) AS _jm,
      GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')) AS email,
      IF(o.kontakt,'-',IFNULL(GROUP_CONCAT(DISTINCT r.emaily),'')) AS emaily,
      SUM(u.u_castka) AS _uhrada,
      platba,p.platba1+p.platba2+p.platba3+p.platba4-vratka1-vratka2-vratka3-vratka4 AS _poplatek
    FROM dopis AS d
      JOIN akce AS a ON d.id_duakce=a.id_duakce
      JOIN pobyt AS p ON d.id_duakce=p.id_akce
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN mail AS m USING (id_pobyt,id_dopis)
      LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      LEFT JOIN uhrada AS u USING (id_pobyt)
      LEFT JOIN tvori AS t USING (id_osoba,id_rodina)
    WHERE d.id_dopis=$id_dopis AND ISNULL(id_mail) $AND
    GROUP BY id_pobyt $HAVING");
  while ( $rr && (list($ido,$idp,$na_akci,$jm,$email,$emaily)= pdo_fetch_array($rr)) ) {
    // osoby pobytu
    if ( !isset($x_po[$idp]) ) $x_po[$idp]= array();
    $x_po[$idp][]= $ido;
    $o_jm[$ido]= $jm;
    // zjištění mailů jednotlivých osob
    $x_om[$ido]= array();
    foreach(preg_split('/\s*[,;]\s*/',$email,0,PREG_SPLIT_NO_EMPTY) as $m)
      $x_om[$ido][]= $m;
    if ( !count($x_om[$ido]) && $emaily!='-' )
      foreach(preg_split('/\s*[,;]\s*/',$emaily,0,PREG_SPLIT_NO_EMPTY) as $m)
        $x_om[$ido][]= $m;
  }
  $n_neobeslani= count($x_po);
  // očištění mailů
  foreach ($x_po as $idp=>$idos) {
    $ms= $delm= '';
    foreach ($idos as $ido) {
      foreach ($x_om[$ido] as $im=>$m) {
        if ( strpos($m,'*')===false ) {
          if ( emailIsValid($m,$chyba) ) {
            $ms.= "$delm$m"; $delm= ',';
          }
          else {
            $err.= "$dele{$o_jm[$ido]} má chybnou adresu $m ($chyba)"; $dele= ', ';
            unset($x_om[$ido][$im]);
          }
        }
        else {
          $err.= "$dele{$o_jm[$ido]} má zneplatněnou adresu $m"; $dele= ', ';
          unset($x_om[$ido][$im]);
        }
      }
    }
    $x_pm[$idp]= $ms;
    if ( $ms ) {
      $n_novi++;
    }
    else {
      $n_err++;
      $err.= "$dele{$o_jm[$ido]} nemá žádnou adresu"; $dele= ', ';
    }
  }
//                                                         debug($x_om); debug($x_po);
  if ( $doplnit ) {
    // zjisti jestli text dopisu obsahuje proměnné
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
    // projdi všechny pobyty s alespoň jedním mailem
    foreach ($x_po as $idp=>$idos) { if ( $x_pm[$idp] ) {
      // pokud dopis obsahuje proměnné, personifikuj obsah
      $priloha= null;
      $body= $is_vars ? mail2_personify($obsah,$vars,$idp,$priloha,$err) : '';
      // a vytvoř mail
      $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body,priloha)
            VALUE (1,'@',0,$id_dopis,{$x_po[$idp][0]},$idp,'{$x_pm[$idp]}','$body','$priloha')";
      $rs= pdo_qry($qr);
      $n_pridano+= pdo_affected_rows($rs);
//                                                         display($qr);
    }}
  }
  // čeština
  $_pobytu= je_1_2_5($n_neobeslani,"pobyt,pobyty,pobytů");
  $ret->html= "Dosud neobeslaných jsou $_pobytu";
  if ( $doplnit ) {
    $_mailu=  je_1_2_5($n_pridano,"mail,maily,mailů");
    $ret->html.= "<br><br><b>Bylo přidáno $_mailu pro dosud neobeslané pobyty?</b>";
    if ( $err ) {
      $_pobytu= je_1_2_5($n_err,"pobyt,pobyty,pobytů");
      $ret->html.= "<br><br><i>Bohužel $_pobytu doplnit nešlo.<br><br>$err</i>";
    }
  }
  else {
    $_mailu=  je_1_2_5($n_novi,"mail,maily,mailů");
    $ret->html.= "<br><br><b>Mám doplnit $_mailu pro neobeslané pobyty?</b>";
    if ( $err ) {
      $_pobytu= je_1_2_5($n_err,"pobyt,pobyty,pobytů");
      $ret->html.= "<br><br><i>Bohužel $_pobytu doplnit nepůjde.<br><br>Problémy: $err</i>";
    }
  }
  return $ret;
}
# ---------------------------------------------------------------------------------- mail2 mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# $dopis_var určuje zdroj adres
#   'U' - (komu=0) rozeslat všem účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze účastnící s funkcí:0,1,2,6 (-,VPS,SVPS,hospodář)
#   'U1'- (komu=4) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastníci bez funkce
#   'U2'- (komu=1) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastnící s funkcí:1,2,6 (VPS,SVPS,hospodář)
#   'U3'- (komu=2) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dlužníci (bez avíza)
#   'U4'- (komu=3) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dospělí s chybějícím nebo zjevně starým OP
#   'U5'- (komu=5) rozeslat všem na akci přítomným vyjma pečounů
#   'U6'- (komu=6) jako U5 ale jen pro prislusnost=CZ/SK
#   'U7'- (komu=7) jako U5 ale jen pro prislusnost!=CZ/SK
#   'U8'- (komu=8) kněží, psycho
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - rozeslat podle mailistu - varianta osoba/rodina
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí mail2_mai_skupina - ZRUŠENO
# $cond = dodatečná podmínka POUZE pro volání z mail2_mai_stav
function mail2_mai_pocet($id_dopis,$dopis_var,$cond='',$recall=false) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni NE';
  $html= '';
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= $nomail= $umrti= '';
  $n= $ns= $nt= $nx= $mx= $nm= $nu= 0;
  $dels= $deln= $delm= $delnm= '';
  $nazev= '';
  switch ($dopis_var) {
  // --------------------------------------------------- mail-list
  case 'G':
    $html.= "Vybraných adresátů ";
    $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
//     list($qry,$ucel)= select('sexpr,ucel','mailist',"id_mailist=$id");
    $ml= mail2_lst_access($id_mailist);
    $result->komu= $komu= $ml->komu;
    // SQL dotaz z mail-listu obsahuje _email,_umrti,_nazev,_id (=id_osoba nebo id_rodina podle komu)
    $res= pdo_qry($ml->sexpr);
    while ( $res && ($d= pdo_fetch_object($res)) ) {
      $n++;
      $nazev= "'{$ml->ucel}'";
      if ( $d->nomail ) {
        // nechce dostávat maily
        $nomail.= "$delnm{$d->_name}"; $delnm= ', '; $nm++;
        continue;
      }
      if (  $d->_umrti ) {
        // nemůže dostávat maily
        $umrti.= "$delnm{$d->_name}"; $delnm= ', '; $nu++;
        continue;
      }
      if ( $d->_email ) {
        if ( $komu=='o' ) {
          // pro osoby přidej každý mail zvlášť do seznamu
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            // pokud tam ještě není
            if ( $adr && !in_array($adr,$emaily) ) {
              if ( $adr[0]=='*' ) {
                // vyřazený mail
                $mimo.= "$delm{$d->_name}"; $delm= ', '; $mx++;
              }
              else {
                $emaily[]= $adr;
                $ids[]= $d->_id;
                $jmena[]= $d->_name;
              }
            }
          }
        }
        else {
          // pro rodiny vytvoř seznamy rodiných mailů
          $r_emaily= array();
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            if ( $adr[0]=='*' ) {
              // vyřazený mail
              $mimo.= "$delm{$d->_name}"; $delm= ', '; $mx++;
            }
            else {
              $r_emaily[]= $adr;
            }
          }
          $emaily[]= implode(',',$r_emaily);
          $ids[]= isset($d->_idr) ? $d->_idr : 0;
          $jmena[]= $d->_name;
        }
      }
      else {
        $nema.= "$deln{$d->_name}"; $deln= ', ';
        $nx++;
      }
    }
    break;
  // --------------------------------------------------- obecný SQL dotaz - skupina
  case 'Q':
    $html.= "Vybraných adresátů ";
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= pdo_qry($qryQ);
    if ( $resQ && ($q= pdo_fetch_object($resQ)) ) {
      $qry= $q->hodnota;
      $qry= mail2_sql_subst($qry);
      $res= pdo_qry($qry);
      while ( $res && ($d= pdo_fetch_object($res)) ) {
        $n++;
        $nazev= "Členů {$q->zkratka}";
        $jm= "{$d->prijmeni} {$d->jmeno}";
        if ( $d->nomail ) {
          // nechce dostávat maily
          $nomail.= "$delnm$jm"; $delnm= ', '; $nm++;
          continue;
        }
        if ( $d->_email ) {
          // přidej každý mail zvlášť do seznamu
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            // pokud tam ještě není
            if ( $adr && !in_array($adr,$emaily) ) {
              if ( $adr[0]=='*' ) {
                // vyřazený mail
                $mimo.= "$delm$jm"; $delm= ', '; $mx++;
              }
              else {
                $emaily[]= $adr;
                $ids[]= $d->_id;
                $jmena[]= $jm;
              }
            }
          }
        }
        else {
          $nema.= "$deln{$d->prijmeni} {$d->jmeno}"; $deln= ', ';
          $nx++;
        }
      }
    }
    break;
  // --------------------------------------------------- účastníci akce
  case 'U8':    // kněží, psychologové
  case 'U7':    // všichni zahraniční přítomní 
  case 'U6':    // všichni tuzemští přítomní 
  case 'U5':    // všichni přítomní (mimo pečounů)
  case 'U4':    // divný OP
  case 'U3':    // dlužníci
  case 'U2':    // sloužící
  case 'U1':    // nesloužící
  case 'U':
    $html.= "Obeslaných účastníků ";
    // zjisti údaje o akci
    list($a_nazev,$ma_cenu,$cena,$hnizda)= select('akce.nazev,ma_cenu,cena,hnizda',
        'dopis JOIN akce USING (id_duakce)',"id_dopis=$id_dopis");
    // POZOR KOPIE KÓDU z mail2_mai_doplnit
    $AND= $cond ? "AND $cond" : '';
    $AND.= $hnizda ? "AND (d.hnizdo=99 || d.hnizdo=p.hnizdo)" : '';
    $AND.= $dopis_var=='U'  ? " AND p.funkce IN (0,1,2,5)" : (
           $dopis_var=='U1' ? " AND p.funkce=0"   : (
           $dopis_var=='U2' ? " AND p.funkce IN (1,2,5)"   : (
           $dopis_var=='U3' ? " AND p.funkce IN (0,1,2,5) " : (
           $dopis_var=='U4' ?
             " AND IF(IFNULL(role,'a') IN ('a','b'),REPLACE(o.obcanka,' ','') NOT RLIKE '^[0-9]{9}$',0)" : (
           $dopis_var=='U5' ? " AND p.funkce NOT IN (9,10,13,14,15,99) " : (
           $dopis_var=='U6' ? " AND p.funkce NOT IN (9,10,13,14,15) AND o.prislusnost IN ('','CZ','SK') " : (
           $dopis_var=='U7' ? " AND p.funkce NOT IN (9,10,13,14,15) AND o.prislusnost NOT IN ('','CZ','SK') " : (
           $dopis_var=='U8' ? " AND p.funkce IN (3,4)" 
         : " --- chybné komu --- " ))))))));
//    $HAVING= $dopis_var=='U3' ? "HAVING _uhrada/_na_akci<cena" : "";
    $HAVING= $dopis_var=='U3' ? ( $ma_cenu 
          ? "HAVING _uhrada/_na_akci<$cena" 
          : "HAVING _uhrada<_poplatek")
        : "";
    // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
    // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech, mají-li osobní
    $qry= "SELECT p.id_pobyt,pouze,COUNT(DISTINCT s.id_osoba) AS _na_akci, 
             (SELECT IFNULL(SUM(u_castka),0) FROM uhrada WHERE id_pobyt=p.id_pobyt) AS _uhrada,
             p.poplatek_d, 
             p.platba1+p.platba2+p.platba3+p.platba4 -vratka1-vratka2-vratka3-vratka4 AS _poplatek,
             GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
             GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno) ORDER BY t.role) AS _jm,
             GROUP_CONCAT(DISTINCT IF(o.kontakt,TRIM(o.email),'')) AS email,
             GROUP_CONCAT(DISTINCT TRIM(r.emaily)) AS emaily
           FROM dopis AS d
           JOIN pobyt AS p ON d.id_duakce=p.id_akce
           JOIN spolu AS s USING (id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
           LEFT JOIN rodina AS r USING (id_rodina)
           LEFT JOIN uhrada AS u USING (id_pobyt)
           WHERE id_dopis=$id_dopis $AND 
             AND IF(i0_rodina,IF(ISNULL(t.role),0,t.role IN ('a','b')),1)
             GROUP BY id_pobyt $HAVING";
    $res= pdo_qry($qry);
    while ( $res && ($d= pdo_fetch_object($res)) ) {
      $n++;
      $nazev= "Účastníků {$a_nazev}";
      list($jm)= explode(',',$d->_jm);
      // kontrola vyřazených mailů
      $eo= $d->email;
      if ( strpos($eo,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $eo= ''; }
      $er= $d->emaily;
      if ( strpos($er,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $er= ''; }
      // pokud je na akci pouze jeden, pošli jen na jeho mail - pokud oba, pošli na všechny maily
      if ( $eo!='' || $er!='' ) {
//         $em= $d->pouze && $eo!='' ? $eo : (             // na akci pouze jeden => osobní mail
        $em= $d->_na_akci==1 && $eo!='' ? $eo : (       // na akci pouze jeden => osobní mail
          $eo!='' && $er!='' ? "$eo,$er" : $eo.$er      // jinak cokoliv půjde
        );
        $emaily[]= $em;
        $pobyty[]= $d->id_pobyt;
        list($ids[])= explode(',',$d->_id);
        list($jmena[])= explode(',',$d->_jm);
      }
      else {
        $nema.= "$deln$jm"; $deln= ', ';
        $nx++;
      }
    }
    break;
  }
  // --------------------------------------------------- projdi a verifikuj adresy
//                                                 debug($emaily,"emaily");
  for ($i= 0; $i<count($ids); $i++) {
    $email= ''; $del= '';
    foreach(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY) as $adr) {
//                                                 debug(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY),$emaily[$i]); break;
      $chyba= '';
//                                                 display("$adr");
      if ( emailIsValid($adr,$chyba) ) {
        $email.= $del.$adr;                     // první dobrý bude adresou
        $del= ',';                              // zbytek pro CC
      }
    }
    if ( $email ) {
      $emaily[$i]= $email;
      $nt++;
    }
    else {                                      // žádný nebyl ok
      $spatne.= "$dels{$jmena[$i]}"; $dels= ', ';
      unset($emaily[$i],$ids[$i],$jmena[$i],$pobyty[$i]);
      $ns++;
    }
  }
  $result->_adresy= $emaily;
  $result->_pobyty= $pobyty;
  $result->_ids= $ids;
  $html.= "$nazev je $n celkem\n";
  $html.= $ns ? "$ns má chybný mail ($spatne)\n" : '';
  $html.= $nx ? "$nx nemají mail ($nema)\n" : '';
  $html.= $nm ? "$nm nechtějí hromadné informace ($nomail)\n" : '';
  $html.= $mx ? "$mx mají mail označený '*' jako nedostupný ($mimo)" : '';
  $html.= $nu ? "$nu zemřelo ($umrti)" : '';
  $result->_html= "$html<br><br>" . ($nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni NE");
  $result->_count= $nt;
  if ( !$recall ) {
    // pro delší seznamy
    $result->_dopis_var= $dopis_var;
    $result->_cond= $cond ? $cond : '';
    $result->_adresy= array();
    $result->_ids= array();
  }
//                                                debug($result,"mail2_mai_pocet.result");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2 mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po mail2_mai_pocet)
# $id_dopis => dopis(&pocet)
# $info = {_adresy,_ids[,_cond]}   _cond
function mail2_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
  $err= '';
//                                                         debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
//                                                         fce_log("mail2_mai_posli: $qry");
  $n= pdo_qry($qry);
  if ( $n ) fce_warning("bylo smazáno $n dříve vygenerovaných mailů");

  if ( isset($info->_dopis_var) ) {
    // přepočítej adresy
    $info= mail2_mai_pocet($id_dopis,$info->_dopis_var,$info->_cond,true);
//     $info->_adresy= $result->_adresy;
  }
  if ( isset($info->_adresy) ) {
    // zjisti text dopisu a jestli obsahuje proměnné
    $obsah= select('obsah','dopis',"id_dopis=$id_dopis");
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
//                                                                 debug($vars);
    // pokud jsou přímo známy adresy, pošli na ně
    $ids= array();
    foreach($info->_ids as $i=>$id) {$ids[$i]= $id;}
    if ( $info->_pobyty ) foreach($info->_pobyty as $i=>$pobyt) {$pobyty[$i]= $pobyt;}
    foreach ($info->_adresy as $i=>$email) {
      $id= $ids[$i];
      // vlož do MAIL - pokud nezačíná *
      if ( $email[0]!='*' ) {
        $id_pobyt= isset($pobyty[$i]) ? $pobyty[$i] : 0;
        // pokud dopis obsahuje proměnné, personifikuj obsah
        $priloha= null;
        $body= $is_vars ? mail2_personify($obsah,$vars,$id_pobyt,$priloha,$err) : '';
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body,priloha)
              VALUE (1,'@',0,$id_dopis,$id,$id_pobyt,'$email','$body','$priloha')";
//                                         display("$i:$qr");
        $num+= pdo_query($qr);
      }
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= mail2_mai_qry($info->_cond);
    $res= pdo_qry($qry);
    while ( $res && $c= pdo_fetch_object($res) ) {
      // zjisti adresy (oddělené ,;) a vyřaď ty uvozené *
      $poslat= array();
      $maily= preg_grep('/,;/', $c->email);
      foreach ($maily as $mail) {
        if (trim($mail)[0]!='*') $poslat[]= $mail;
      }
      // vlož do MAIL
      $poslat= implode(',',$poslat);
      if ( $poslat ) {
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
              VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
        $rs= pdo_qry($qr);
        $num+= pdo_affected_rows($rs);
      }
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
//                                                         fce_log("mail2_mai_posli: UPDATE");
  $rs= pdo_qry($qr);
  // získání případného omezení použitého SMTP
  global $ezer_root;
  $idu= $_SESSION[$ezer_root]['user_id'];
  $i_smtp= sys_user_get($idu,'opt','smtp');
  if (!$i_smtp) {
    $err.= "<hr>Není přístupný žádný odesílací SMTP server"; 
    goto end;
  }
  $max_per_day= select1('ikona','_cis',"druh='smtp_srv' AND data='$i_smtp'");
  // případné upozornění na maximum
  if ( $max_per_day && $info->_count>$max_per_day ) {
    $err.= "<hr>Přes GMail lze denně lze posílat maximálně $max_per_day mailů - 
        před dosažením maxima bude odesílání přerušeno (před reakcí GMailu). 
        Pokračujte potom prosím v odesílání další den (nebo po určité době).
        Informace od Google naleznete 
        <a href='https://support.google.com/mail/answer/22839?hl=cs' target='hlp'>zde</a>.";
  }
end:  
  return $err;
}
# ---------------------------------------------------------------------------------- mail2 personify
# spočítá proměnné podle id_pobyt a dosadí do textu dopisu
# vrátí celý text
function mail2_personify($obsah,$vars,$id_pobyt,&$priloha,&$err) { 
    debug($vars,"mail2_personify(...,$vars,$id_pobyt,...) ");
  global $ezer_path_root;
  $text= $obsah;
  $priloha= '';
  list($duvod_typ,$duvod_text,$id_hlavni,$id_soubezna,
       $platba1,$platba2,$platba3,$platba4,$poplatek_d,$skupina,$idr)=
    select('duvod_typ,duvod_text,IFNULL(id_hlavni,0),id_duakce,
      platba1,platba2,platba3,platba4,poplatek_d,skupina,i0_rodina',
    "pobyt LEFT JOIN akce ON id_hlavni=pobyt.id_akce",
    "id_pobyt=$id_pobyt");
  foreach($vars as $var) {
    $val= '';
    switch ($var) {
    case 'foto_z_akce':
      $fotky= select('fotka','rodina',"id_rodina=$idr");
      if ($fotky) {
        $del= '';
        $nazvy= explode(',',$fotky);
        foreach ($nazvy as $nazev) {
          $foto= "$ezer_path_root/fotky/$nazev";
          if (file_exists($foto) ) {
            $date= @filemtime($foto);
            $ymd= date('Y-m-d',$date);
            $na_akci= select('COUNT(*)','akce JOIN pobyt ON id_akce=id_duakce',
                "id_pobyt=$id_pobyt AND '$ymd' BETWEEN datum_od AND datum_do");
            if ($na_akci) {
              $priloha.= "{$del}fotky/$nazev"; $del=',';
            }
          }
        }
      }
      break;
    case 'pratele':
      $val= $idr ? select('nazev','rodina',"id_rodina=$idr") : 'přátelé';
      break;
    case 'akce_cena':
//      // zjisti, zda je cena stanovena
//      if (($platba1+$platba2+$platba3+$platba4+$poplatek_d)==0) {
//        // není :-(
//        $err.= "<br>POZOR: všichni účastníci nemají stanovenu cenu (pobyt=$id_pobyt)";
//        break;
//      }
      if ( $duvod_typ ) {
        $val= $duvod_text;
      }
      elseif ( $id_hlavni ) {
        $ret= akce2_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna);
        $val= $ret->mail;
      }
      else {
        $ret= akce2_vzorec($id_pobyt);
        $val= $ret->mail;
      }
      // zjisti, zda je cena stanovena
      if (!$val) {
        $nazev= select('nazev','rodina',"id_rodina=$idr");
        $err.= "<br>POZOR: všichni účastníci nemají stanovenu cenu (pobyt=$id_pobyt, $nazev)";
      }
      break; 
    case 'skupinka_chlapi':
      if ($skupina) {
        $ida= select('id_akce','pobyt',"id_pobyt=$id_pobyt");
        $res= akce2_starsi_mrop_pdf($ida,$id_pobyt);
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>$res->skupina</div>";
      }
      else {
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>Skupinka ještě není vybrána</div>";
      }
      break;
    case 'skupinka_popo':
      if ($skupina) {
        $ida= select('id_akce','pobyt',"id_pobyt=$id_pobyt");
        $tab= "<table>";
        $s= pdo_qry("
            SELECT 
              CONCAT(nazev,' ',GROUP_CONCAT(o.jmeno ORDER BY t.role SEPARATOR ' a ')) AS _nazev,
              GROUP_CONCAT(IF(kontakt,IF(o.telefon!='',o.telefon,'?'),r.telefony) 
                ORDER BY t.role SEPARATOR ' a ') AS telefon, 
              GROUP_CONCAT(IF(kontakt,IF(o.email!='',o.email,'?'),r.emaily) 
                ORDER BY t.role SEPARATOR ' a ') AS email
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE p.id_akce=$ida AND skupina=$skupina AND IF(ISNULL(r.id_rodina),1,t.role IN ('a','b'))
            GROUP BY id_pobyt
            ORDER BY IF(funkce IN (1,2),1,2), _nazev        
          ");
        while ($s && (list($par,$tel,$mail)= pdo_fetch_row($s))) {
          $tab.= "<tr><th>$par</th><td>$tel</td><td>$mail</td></tr>";
        }
        $tab.= "</table>";
        $val= "<big><b><u>SKUPINKA $skupina</u></b></big> $tab";
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>$val</div>";
      }
      else {
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>Skupinka ještě není vybrána</div>";
      }
      break;
    }
    $text= str_replace('{'.$var.'}',$val,$text);
  }
  $text= pdo_real_escape_string($text);
  return $text;
}
# ----------------------------------------------------------------------------- mail2 personify_help
# vrátí popis možných personifikací
function mail2_personify_help() {
  $html= "
    <b>{pratele}</b> vloží název rodiny, pokud na akci není rodina, vloží slovo 'přátelé'<br>
    <b>{akce_cena}</b> pokud má akce definovaný ceník, vloží rozpis platby účastníka<br><br>
    <b>{skupinka_popo}</b> pro VPS vloží seznam členů skupiny s maily a telefony<br>
    <b>{skupinka_chlapi}</b> pro staršího/stokera vloží seznam členů skupiny s maily a telefony<br>
    <b>{foto_z_akce}</b> pokud byla na akci vložena fotografie rodiny, přidá ji jako přílohu<br>
    <br>
    ";
//    <b>{mistnost_popo}</b> pro VPS vloží odkaz na konrefenční místnost<br>
  return $html;
}
# ----------------------------------------------------------------------------------- mail2 mai_info
# informace o členovi
# $id - klíč osoby nebo chlapa
# $zdroj určuje zdroj adres
#   'U','U1','U2','U3','U4','U5','U6','U7','U8' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#   'C' - rozeslat účastníkům akce dopis.id_duakce ukazující do ch_ucast
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - maillist
function mail2_mai_info($id,$email,$id_dopis,$zdroj,$id_mail) {  //trace();
  $html= '';
  $make_href= function ($fnames) {
    global $ezer_root;
    $href= array();
    foreach(explode(',',$fnames) as $fnamesize) {
      list($fname)= explode(':',$fnamesize);
      $has_dir= strrpos($fname,'/');
      $path= $has_dir ? $fname : "docs/$ezer_root/";
      if ($has_dir) $fname= substr($fname,$has_dir+1);
      $href[]= "<a href='$path' target='pdf'>$fname</a>";
    }
    return implode(', ',$href);
  };
  switch ($zdroj) {
  case 'C':                     // chlapi
    $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) {
      $html.= "{$c->prijmeni} {$c->jmeno}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefon )
        $html.= "Telefon: {$c->telefon}<br>";
    }
    break;
  case 'Q':                     // číselník
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka,_cis.barva FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= pdo_qry($qryQ);
    if ( $resQ && ($q= pdo_fetch_object($resQ)) ) {
//       if ( $q->barva==1 ) {
//         // databáze CHLAPI
//         $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
//         $res= pdo_qry($qry);
//         if ( $res && $c= pdo_fetch_object($res) ) {
//           $html.= "{$c->prijmeni} {$c->jmeno}<br>";
//           $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
//           if ( $c->telefon )
//             $html.= "Telefon: {$c->telefon}<br>";
//         }
//       }
//       else
      if ( $q->barva==4 ) {
        // kopie databáze DS = ds_osoba_copy
        $qry= "SELECT * FROM ds_osoba_copy WHERE id_osoba=$id ";
        $res= pdo_qry($qry);
        if ( $res && $c= pdo_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==2 ) {
        // databáze osob
        $qry= "SELECT role, prijmeni,jmeno,
            IF(kontakt,telefon,IFNULL(r.telefony,'?')) AS telefon,
            IF(adresa,o.ulice,IFNULL(r.ulice,'?')) AS ulice,
            IF(adresa,o.psc,IFNULL(r.psc,'?')) AS psc,
            IF(adresa,o.obec,IFNULL(r.obec,'?')) AS obec,
            IF(adresa,o.stat,IFNULL(r.stat,'?')) AS stat
          FROM osoba AS o LEFT JOIN tvori AS t USING (id_osoba)
          LEFT JOIN rodina AS r USING (id_rodina) WHERE id_osoba=$id
          ORDER BY t.role  LIMIT 1 ";
        $res= pdo_qry($qry);
        if ( $res && $c= pdo_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}, {$c->stat}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      else {
        // databáze MS
        // SELECT vrací (_id,prijmeni,jmeno,ulice,psc,obec,email,telefon)
        $qry= $q->hodnota;
        $qry= mail2_sql_subst($qry);
        if ( strpos($qry,"GROUP BY") ) {
          if ( strpos($qry,"HAVING") )
            $qry= str_replace("HAVING","HAVING _id=$id AND ",$qry);
          else
            $qry= str_replace("GROUP BY","GROUP BY _id HAVING _id=$id AND ",$qry);
          // zatém jen pro tuto větev
          $res= pdo_qry($qry);
          while ( $res && ($c= pdo_fetch_object($res)) ) {
            $html.= "{$c->prijmeni} {$c->jmeno}<br>";
            $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
            if ( $c->telefon )
              $html.= "Telefon: {$c->telefon}<br>";
          }
        }
        else {
          // způsobuje chybu  GROUP BY vyžaduje nějakou agregační funkci
//           $qry.= " GROUP BY _id HAVING _id=$id ";
        }
      }
    }
    break;
  case 'U':                     // účastníci akce
  case 'U8':                    // kněží, psycho
  case 'U5':                    // všichni přítomní vyjma pečounů
  case 'U6':                    // všichni tuzemští přítomní 
  case 'U7':                    // všichni zahraniční přítomní 
  case 'U1':                    // nesloužící účastníci akce
  case 'U2':                    // sloužící účastníci akce
  case 'U3':                    // dlužníci
  case 'U4':                    // divný OP
    $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) {
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
    }
    $prilohy= select('prilohy','dopis',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".$make_href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".$make_href($priloha);
    break;
  case 'G':                     // mail-list
    list($obsah,$prilohy,$komu)= select('obsah,prilohy,mailist.komu',
      'dopis JOIN mailist USING (id_mailist)',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    if ( $komu=='o' ) {
      $c= select("*",'osoba',"id_osoba=$id");
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}";
      $html.= $c->adresa ? "<br>{$c->ulice}, {$c->psc} {$c->obec}" : '';
      if ( $c->kontakt && $c->telefon ) $html.= $c->kontakt ? "<br>Telefon: {$c->telefon}<br>" : '';
    }
    else { // rodina
      $c= select("*",'rodina',"id_rodina=$id");
      $html.= "{$c->id_rodina}: {$c->nazev}";
      $html.= "<br>{$c->ulice}, {$c->psc} {$c->obec}";
      if ( $c->telefony ) $html.= "<br>Telefon: {$c->telefony}<br>";
    }
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".$make_href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".$make_href($priloha);
    break;
  }
  return $html;
}
# ----------------------------------------------------------------------------------- mail2 mai_smaz
# smazání mailu v DOPIS a jeho rozesílání v MAIL
function mail2_mai_smaz($id_dopis) {  trace();
  query("DELETE FROM dopis WHERE id_dopis=$id_dopis ");
  query("DELETE FROM mail WHERE id_dopis=$id_dopis ");
  return true;
}
# ----------------------------------------------------------------------------------- mail2 mai_stav
# úprava stavu mailové adresy
# ZATIM BEZ: (maže maily - nutné zohlednit i id_clen==id_osoba aj.) včetně znovuzískání mailové adresy s karty účastníka
function mail2_mai_stav($id_mail,$stav) {  trace();

  list($id_dopis,$id_pobyt)= select("id_dopis,id_pobyt","mail","id_mail=$id_mail");
  $novy_mail= '';
//   if ( $id_pobyt) {
//     $oprava= mail2_mai_pocet($id_dopis,'U',$cond="id_pobyt=$id_pobyt");
//     $emaily= $oprava->_adresy[0];
//     $novy_mail= ",email='$emaily'";
//                                                   debug($oprava,"mail2_mai_stav:$emaily.");
//   }
  $qry= "UPDATE mail SET stav=$stav$novy_mail WHERE id_mail=$id_mail ";
  $res= pdo_qry($qry);
  if ( !$res ) fce_error("mail2_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# ------------------------------------------------------------------------------ mail2 new_PHPMailer
# nastavení parametrů pro SMTP server podle user.options.smtp
# nebo přímo zadáním parametrů
function mail2_new_PHPMailer($smtp=null) {  
  global $ezer_path_serv, $ezer_root;
  $mail= null;
  if (!$smtp) {
    // získání parametrizace SMTP
    $idu= $_SESSION[$ezer_root]['user_id'];
    $i_smtp= sys_user_get($idu,'opt','smtp');
    $smtp_json= select1('hodnota','_cis',"druh='smtp_srv' AND data=$i_smtp");
    $smtp= json_decode($smtp_json);
    if ( json_last_error() != JSON_ERROR_NONE ) {
      fce_warning("chyba ve volbe SMTP serveru" . json_last_error_msg());
      goto end;
    }
  }
  // inicializace phpMailer
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.phpmailer.php");
  require_once("$phpmailer_path/class.smtp.php");
  $mail= new PHPMailer;
  $mail->SetLanguage('cs',"$phpmailer_path/language/");
  $mail->IsSMTP();
  $mail->CharSet = "UTF-8";
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  foreach ($smtp as $part=>$value) {
  	if ($part=="SMTPOptions" && $value=="-")
      $mail->SMTPOptions= array('ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true));
  	else
      $mail->$part= $value;
  }
end:  
  return $mail;
}
# -------------------------------------------------------------------------------- mail2 mai_sending
// y je paměť procesu, který bude krok za krokem prováděn 
// y.todo - celkový počet kroků
// y.done - počet provedených kroků 
// y.sent - počet skutečně odeslaných mailů
// y.error = text chyby, způsobí konec
function mail2_mai_sending($y) { 
  global $ezer_root;
  // získání případného omezení použitého SMTP
  $idu= $_SESSION[$ezer_root]['user_id'];
  $i_smtp= sys_user_get($idu,'opt','smtp');
  $max_per_day= select1('ikona','_cis',"druh='smtp_srv' AND data=$i_smtp");
  // pokud je y.todo=0 provede se inicializace procesu podle y.par
  if ( $y->todo==0 ) {
    $_SESSION[$ezer_root]['mail_par']= $y->par;
    $y->done= 0;
    $n= select('COUNT(*)','mail',"id_dopis={$y->par->id_dopis} AND stav IN (0,3)");
    $y->todo= $y->par->davka ? ceil($n/$y->par->davka) : 0;
    $y->last= 0; // poslední poslaný id_mail
    $y->sent= 0; // počet poslaných
    $y->error= '';
    unset($y->par);
  }
  if ( $y->error ) { goto end; }
  if ( $y->done >= $y->todo ) { $y->done= $y->todo; $y->msg= 'konec+'; goto end; }
  $par= (object)$_SESSION[$ezer_root]['mail_par'];
  // pokud by odeslání překročilo omezení ukonči je
   if ( $max_per_day && ($y->sent+$par->davka)>$max_per_day ) {
     $res->max= $max_per_day;
   } 
  // vlastní proces
  $res= mail2_mai_send($par->id_dopis,$par->davka,$par->from,$par->name,'',0,$par->foot);
  $y->done++;
  $y->sent= $res->_sent;
  // zpráva
  $y->msg= $y->done==$y->todo ? 'konec' : "ještě ".($y->todo-$y->done)." x {$par->davka}"; 
  // poslední mail pro refresh
  $y->last= $res->_last;
  if ( $res->_error ) {
    if ($res->_over_quota) {
      $y->error= "<b style='color:#700;background:#ff0'>Byla překročena kvóta pro odesílání GMailů. 
        Pokračujte zítra.</b>";
    }
    else {
      $y->error= $res->_html;
    }
    goto end;
  }
  // před skončením počkej 1s aby šlo velikostí dávky řídit zátěž
  sleep(1);
end:  
  return $y;
}
# ----------------------------------------------------------------------------------- mail2 mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
# $from,$fromname = From,ReplyTo
# $test = 1 mail na tuto adresu (pokud je $kolik=0)
# pokud je definováno $id_mail s definovaným text MAIL.body, použije se - jinak DOPIS.obsah
# pokud je definováno $foot tj. patička, připojí se na konec
# použije se SMTP server podle SESSION
function mail2_mai_send($id_dopis,$kolik,$from,$fromname,$test='',$id_mail=0,$foot='') { trace();
  $TEST= 0;
  // připojení případné přílohy
  $attach= function($mail,$fname) {
    global $ezer_root;
    if ( $fname ) {
      foreach ( explode(',',$fname) as $fnamesb ) {
        list($fname,$bytes)= explode(':',$fnamesb);
        $fname= trim($fname);
        $has_dir= strrpos($fname,'/');
        $fpath= $has_dir ? $fname : "docs/$ezer_root/$fname";
//        if ($has_dir) $fname= substr($fname,$has_dir+1);
//        $fpath= "docs/$ezer_root/".trim($fname);
        $mail->AddAttachment($fpath);
  } } };
  //
  $result= (object)array('_error'=>0,'_sent'=>0,'_over_quota'=>0);
  $pro= '';
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= pdo_qry($qry,1,null,1);
  $d= pdo_fetch_object($res);
  // napojení na mailer
  $html= '';
//   $klub= "klub@proglas.cz";
  $martin= "martin@smidek.eu";
//   $jarda= "cerny.vavrovice@seznam.cz";
//   $jarda= $martin;
  // poslání mailů
  $mail= mail2_new_PHPMailer();
  if ( !$mail ) { 
    $result->_html.= "<br><b style='color:#700'>odesílací adresa nelze použít (SMTP)</b>";
    $result->_error= 1;
    goto end;
  }
  // test odesílací adresy -- pro maily pod seznam.cz musí být stejná jako přihlašovací
  $mail->From= preg_match("/@chlapi.cz|@seznam.cz/",$mail->Username) ? $mail->Username : $from;
  $mail->AddReplyTo($from);
//   $mail->ConfirmReadingTo= $jarda;
  $mail->FromName= "$fromname";
  $mail->Subject= $d->nazev;
//                                         display($mail->Subject);
  $attach($mail,$d->prilohy);
//   if ( $d->prilohy ) {
//     foreach ( explode(',',$d->prilohy) as $fnamesb ) {
//       list($fname,$bytes)= explode(':',$fnamesb);
//       $fpath= "docs/$ezer_root/".trim($fname);
//       $mail->AddAttachment($fpath);
//     }
//   }
  if ( $kolik==0 ) {
    // testovací poslání sobě
    if ( $id_mail ) {
      // přečtení personifikace rozesílaného mailu
      $qry= "SELECT * FROM mail WHERE id_mail=$id_mail ";
      $res= pdo_qry($qry,1,null,1);
      $m= pdo_fetch_object($res);
      if ( $m->body ) {
        $obsah= $m->body;
        $pro= "s personifikací pro {$m->email}";
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
        $pro= '';
      }
      $attach($mail,$m->priloha);
    }
    $mail->Body= $obsah . $foot;
    $mail->AddAddress($test);   // pošli sám sobě
//    // pseudo dump 
//    $mail->SMTPDebug= 3;
//    $mail->Debugoutput = function($str, $level) { display("debug level $level; message: $str");};
//    $pars= (object)array();
//    foreach (explode(',',"Mailer,Host,Port,SMTPAuth,SMTPSecure,Username,From,AddReplyTo,FromName,SMTPOptions") as $p) {
//      $pars->$p= $mail->$p;
//    }
//    debug($pars,"nastavení PHPMAILER");
    // pošli
    if ( $TEST ) {
      $ok= 1;
    }
    else {
      // zkus poslat mail
      try { $ok= $mail->Send(); } 
      catch(Exception $e) { 
        $ok= false; 
      }
    }
    if ( $ok  )
      $html.= "<br><b style='color:#070'>Byl odeslán mail na $test $pro - je zapotřebí zkontrolovat obsah</b>";
    else {
      $err= $mail->ErrorInfo;
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $html.= "<br><b style='color:#700'>Při odesílání mailu přes '$ze' došlo k chybě: $err</b>";
      display("Send failed: $err<br>from={$mail->From} username={$mail->Username} SMTPserver=$ze");
      $result->_error= 1;
    }
//                                                 display($html);
  }
  else {
    // poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav IN (0,3) ORDER BY email";
    $res= pdo_qry($qry);
    while ( $res && ($z= pdo_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $result->_last= $z->id_mail; // pro refresh
      $i= 0;
      $mail->ClearAddresses();
      $mail->ClearCCs();
      if ( $z->body ) {
        // pokud má mail definován obsah (personifikovaný mail) ber z MAIL
        $obsah= $z->body;
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
      }
      // přílohy - pokud jsou vlastní, pak je třeba staré vymazat a vše vložit
      if ( $z->priloha ) {
        $mail->ClearAttachments();
        $attach($mail,$d->prilohy);
        $attach($mail,$z->priloha);
      }
      $mail->Body= $obsah . $foot;
      foreach(preg_split("/,\s*|;\s*|\s+/",trim($z->email," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
//       $mail->AddBCC($klub);
       if ( $TEST ) {
         $ok= 1;
                                          display("jako odeslaný mail pro $adresa");
       }
       else {
        // zkus poslat mail
        try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
      }
      if ( !$ok  ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $err= $mail->ErrorInfo;
        $html.= "<br><b style='color:#700'>Při odesílání mailu pro $ident došlo k chybě: $err</b>";
        $result->_error= 1;
        $nko++;
      }
      else {
        $n++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok ? 4 : 5;
      $msg= $ok ? '' : $mail->ErrorInfo;
      if (preg_match("/Daily user sending quota exceeded/",$msg)) {
        $result->_over_quota= 1;
      }
      else {
        $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
        $res1= pdo_qry($qry1);
      }
      // po chybě přeruš odesílání
      if ( !$ok ) break;
    }
    $result->_sent= $n;
    $html.= "<br><b style='color:#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
//                                                 debug($result,"mail2_mai_send");
end:  
  return $result;
}
# --------------------------------------------------------------------------------- mail2 mai_attach
# přidá další přílohu k mailu (soubor je v docs/$ezer_root)
function mail2_mai_attach($id_dopis,$f) { trace();
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $names= ($names ? "$names," : '')."{$f->name}:{$f->size}";
  query("UPDATE dopis SET prilohy='$names' WHERE id_dopis=$id_dopis");
  return 1;
}
# ----------------------------------------------------------------------------- mail2 mai_detach_all
# odstraní všechny přílohy mailu
function mail2_mai_detach_all($id_dopis) { trace();
  query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
  return 1;
}
# --------------------------------------------------------------------------------- mail2 mai_detach
# odebere soubor z příloh
function mail2_mai_detach($id_dopis,$name) { trace();
  // nalezení záznamu v tabulce a odebrání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $as= explode(',',$names);
  $as2= array();
  foreach($as as $a) {
    list($an,$ab)= explode(':',$a);
    if ( $an!=$name )$as2[]= $a;
  }
  $names2= implode(',',$as2);
  query("UPDATE dopis SET prilohy='$names2' WHERE id_dopis=$id_dopis");
  return 1;
}
# =====================================================================================> . SQL maily
# vytváření a testování SQL dotazů pro definici mailů
# ------------------------------------------------------------------------------------ mail2 copy_ds
# ASK - kopie tabulky SETKANI.DS_OSOBA do EZER_YS.DS_OSOBA_COPY
# vrací {id_cis,data,query}
function mail2_copy_ds() {  trace();
  global $ezer_db;
  $html= 'kopie se nepovedla';
  // smazání staré kopie
  $qry= "TRUNCATE TABLE /*ezer_ys.*/ds_osoba_copy ";
  $ok= pdo_qry($qry);
  if ( $ok ) {
    $html= "inicializace ds_osoba_copy ok";
    ezer_connect('setkani');
    $qrs= "SELECT * FROM ds_osoba WHERE email!='' ";
    $res= pdo_qry($qrs);
    while ( $res && ($s= pdo_fetch_object($res)) ) {
//                                                         debug($s,'s',(object)array('win1250'=>1));
      $ids= $vals= $del= '';
      foreach($s as $id=>$val) {
        $ids.= "$del$id";
        $vals.= "$del'".pdo_real_escape_string(wu($val))."'";
        $del= ',';
      }
      $qry= "INSERT INTO /*ezer_ys.*/ds_osoba_copy ($ids) VALUES ($vals)";
      $ok= pdo_query($qry,$ezer_db['ezer_ys'][0]);
//                                                         display("$ok:$qry");
      if ( !$ok ) {
        $html.= "\nPROBLEM ".pdo_error();
      }
    }
    if ( $ok ) {
      $html.= "\nkopie do ds_osoba_copy ok";
    }
  }
  return $html;
}
# ------------------------------------------------------------------------------------ mail2 sql_new
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_new() {  #trace();
  $id= select("MAX(0+id_cis)","_cis","druh='db_maily_sql'");
  $data= select("MAX(0+data)","_cis","druh='db_maily_sql'");
  $result= (object)array(
    'id'=>$id+1, 'data'=>$data+1,
    'qry'=>"SELECT id_... AS _id,prijmeni,jmeno,ulice,psc,obec,email,telefon FROM ...");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2 sql_subst
# ASK - parametrizace SQL dotazů pro definici mailů, vrací modifikovaný dotaz
# nebo pokud je prázdný tak přehled možných parametrizací dotazu
function mail2_sql_subst($qry='') {  trace();
  global $USER;
  $org= $USER->org==1 ? "1 /*YMCA Setkání*/" : "2 /*YMCA Familia*/";
  // parametry
  $parms= array (
   'org'   => array ($org,'moje organizace'),
   'letos' => array (date('Y'),'letošní rok'),
   'vloni' => array (date('Y')-1,'loňský rok'),
   'pred2' => array (date('Y')-2,'předloni'),
   'pred3' => array (date('Y')-3,'před 3 lety'),
   'pred4' => array (date('Y')-4,'před 4 lety'),
   'pred5' => array (date('Y')-5,'před 5 lety'),
   'pred6' => array (date('Y')-6,'před 6 lety')
  );
  if ( $qry=='' ) {
    // help
    $del= '';
    foreach ($parms as $parm=>$value) {
      $qry.= "$del\$$parm = {$value[1]} ({$value[0]})";
      $del= '<br>';
    }
  }
  else {
    // substituce
    foreach ($parms as $parm=>$value) {
      $qry= str_replace("\$$parm",$value[0],$qry);
    }
  }
  return $qry;
}
# ------------------------------------------------------------------------------------ mail2 sql_try
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_try($qry,$vsechno=0,$export=0) {  trace();
  $html= $head= $tail= '';
  $emails= array();
  try {
    // export?
    $href= '';
    if ( $export ) {
      $fname= "skupina_".date("Ymd_Hi").".csv";
      $fpath= "docs/$fname";
      $flds= "příjmení jméno;email;telefon;ulice, psč obec;v;w;x;y;z";
      $f= @fopen($fpath,'w');
      if ( !$f ) fce_error("soubor '$fpath' nelze vytvořit");
      fputs($f,chr(0xEF).chr(0xBB).chr(0xBF));
      fputcsv($f,explode(';',$flds),';','"');
      $href= ". Seznam <a href='$fpath'>$fname</a> lze stáhnout do Excelu ve formátu CSV";
    }
    // substituce
    $qry= mail2_sql_subst($qry);
    // dotaz
    $time_start= getmicrotime();
    $res= pdo_qry($qry);
    $time= round(getmicrotime() - $time_start,4);
    if ( !$res ) {
      $html.= "<span style='color:darkred'>ERROR ".pdo_error()."</span>";
    }
    else {
      $nmax= $vsechno ? 99999 : 200;
      $num= pdo_num_rows($res);
      $head.= "Výběr obsahuje <b>$num</b> emailových adresátů, nalezených během $time ms, ";
      $head.= $num>$nmax ? "následuje prvních $nmax adresátů" : "následují všichni adresáti";
      $tail.= "<br><br><table class='stat'>";
      $tail.= "<tr><th>prijmeni jmeno</th><th>email</th><th>telefon</th>
        <th>ulice psc obec</th><th>v</th><th>w</th><th>x</th><th>y</th><th>z</th></tr>";
      $n= $nmax;
      while ( $res && ($c= pdo_fetch_object($res)) ) {
        if ( $n ) {
          $tail.= "<tr><td>{$c->prijmeni} {$c->jmeno}</td><td>{$c->_email}</td><td>{$c->telefon}</td>
            <td>{$c->ulice}, {$c->psc} {$c->obec}</td>
            <td>{$c->_v}</td><td>{$c->_w}</td><td>{$c->_x}</td><td>{$c->_y}</td><td>{$c->_z}</td></tr>";
          if ( $export ) {
            fputcsv($f,array("$c->prijmeni $c->jmeno",$c->_email,$c->telefon,
                "{$c->ulice}, {$c->psc} {$c->obec}",$c->_v,$c->_w,$c->_x,$c->_y,$c->_z),';','"');
          }
          $n--;
        }
        // počítání mailů
        $es= preg_split('/[,;]/',str_replace(' ','',$c->_email));
        foreach($es as $e) {
          if ( $e!='' && !in_array($e,$emails) ) $emails[]= $e;
        }
      }
      $tail.= "</table>";
      $tail.= $num>$nmax ? "..." : "";
    }
    if ( $export ) {
      fclose($f);  
    }
  }
  catch (Exception $e) { $html.= "<span style='color:red'>FATAL ".pdo_error()."</span>";  }
  $head.= "<br>Adresáti mají <b>".count($emails)."</b> různých emailových adres $href";
  $html= $html ? $html : $head.$tail;
//                                                 debug($emails,"db_mail_sql_try");
  return $html;
}
# =================================================================================> . Generátor SQL
# ---------------------------------------------------------------------------------- mail2 gen_excel
# vygeneruje do Excelu seznam adresátů
function mail2_gen_excel($gq,$nazev) { trace();
  global $ezer_root, $ezer_version;
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $href= "CHYBA!";
  // úprava dotazu
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
//                                                         display($gq);
  // export do Excelu
  // zahájení exportu
  $ymd_hi= date('Ymd_Hi');
  $dnes= date('j. n. Y');
  $t= "mail-list $nazev, stav ke dni $dnes";
  $file= "maillist_$ymd_hi";
  $type= 'xls';
  $par= (object)array('dir'=>$ezer_root,'file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "_name:příjmení jméno,_email:email,_ulice:ulice,_psc:PSČ,_obec:obec,_stat:stát,_ucasti:účastí";
  if ( preg_match("/iniciace/i",$gq) ) {
    // přidání sloupce iniciace, pokud se vyskytuje v dotazu
    $clmns.= ",iniciace:iniciace";
  }
  $clmns.= ",_id:ID";
  $titles= $del= '';
  $fields= $values= array();
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields[]= $field;
    $values[$field]= "";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles,":: bcolor=ffc0e2c2 wrap border=+h");
  // dotaz
  $gr= @pdo_query($gq);
  if ( !$gr ) { fce_warning(pdo_error()); goto end; }
  while ( $gr && ($g= pdo_fetch_object($gr)) ) {
//                                                 display('');
    foreach ($g as $f => $val) {
      if ( in_array($f,$fields) ) {
        $a= $val;
        if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
        $values[$f]= $a;
                                                display_("$a ");
      }
    }
    export_row($values,":: border=+h");
  }
  export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $href= "soubor pro <a href='docs/$ezer_root/$file.$type' target='xls'>Excel</a>";
end:
  return $href;
}
/** ========================================================================================> DOPISY */
# =======================================================================================> . šablony
# ------------------------------------------------------------------------------------- dop sab_text
# přečtení běžného dopisu daného typu
function dop_sab_text($dopis) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis,obsah FROM dopis WHERE typ='$dopis' AND id_davka=1 ";
    $res= pdo_qry($qry,1,null,1);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_text: průběžný dopis '$dopis' nebyl nalezen"); }
  return $d;
}
# ------------------------------------------------------------------------------------- dop sab_cast
# přečtení části šablony
function dop_sab_cast($druh,$cast) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis_cast,obsah FROM dopis_cast WHERE druh='$druh' AND name='$cast' ";
    $res= pdo_qry($qry,1,null,1);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_cast: část '$cast' sablony nebyla nalezena"); }
  return $d;
}
# ----------------------------------------------------------------------------------- dop sab_nahled
# ukázka šablony
function dop_sab_nahled($k3) { trace();
  global $ezer_path_docs;
  $html= '';
  $fname= "sablona.pdf";
  $f_abs= "$ezer_path_docs/$fname";
  $f_rel= "docs/$fname";
//   ezer_connect('ezer_ys');
  $html= tc_sablona($f_abs,'','D');                 // jen části bez označení v dopis_cast.pro
  $date= @filemtime($f_abs);
  $href= "<a target='dopis' href='$f_rel'>$fname</a>";
  $html.= "Byl vygenerován PDF soubor: $href (verze ze ".date('d.m.Y H:i',$date).")";
  $html.= "<br><br>Jméno za 'vyřizuje' se bere z osobního nastavení přihlášeného uživatele.";
  return $html;
}
# =====================================================================================> . potvrzení
# ---------------------------------------------------------------------------------------- ucet_potv
# přehled podle tabulky 'prijate dary' na intranetu
function ucet_potv($par,$access) { trace();
  $html= $href= '';
  $mez_daru= 500;       // pod tuto částku neposíláme potvrzení - je třeba opravit v mailist.sexpr
  $darce= array();
  if ( $access!=1  ) {
    $html.= "POZOR: přehled darů na GoogleDisk je (zatím) pouze pro Setkání";
    goto end;
  }
  $max= 9999;
//   $max= 30;
  $rok= date('Y')+$par->rok;
  $let18= date('Y')-18;

//  global $json; 
//  $key= "1KG943OiuVeb_S7FuCZdhPWF3fNu44hBiR2DZIsp3Pok";         // prijate_dary
  $key= "1mwdOhCV3LLAhwbUAl2fxzwjselZjs1lWqMQXJRxLpF8";         // prijate_dary_pro_potvrzeni
  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
  $sheet= $rok;
//   $sheet= "Test$rok";
  $prijate_dary= "<a target='xls' href='https://drive.google.com/open?id=$key'>prijate_dary_pro_potvrzeni</a>";
  $url= "https://docs.google.com/spreadsheets/d/$key/gviz/tq?tqx=out:json&sheet=$sheet";
                                        display($url);
  $x= file_get_contents($url);
//                                         display($x);
  $xi= strpos($x,$prefix);
  $xl= strlen($prefix);
//                                         display("xi=$xi,$xl");
  $x= substr(substr($x,$xi+$xl),0,-2);
//                                         display($x);
  $goo= json_decode($x)->table;
//                                         debug($goo,$sheet);
  // výběr záznamů o darech
  $tab= $clmn= array();
  $prblm1= $prblm2= '';
  $jmeno_prvni= array();  // ke klíči $prijmeni$jmeno dá řádek s prvním výskytem
  $jmeno_id= array();     // ke klíči $prijmeni$jmeno dá id nebo 0
  $nalezeno= 0;
  $radku= count($goo->rows);
  //$radku= 300;
  for ($i= 1; $i<$radku; $i++) {
    $i1= $i+1;
    $grow= $goo->rows[$i]->c;
    $row= (object)array();
    $datum=     $row->a= $grow[0]->v;
    $dar_jmeno= $row->b= $grow[1]->v;
    $castka=    $row->c= $grow[2]->v;
    $ref=       $row->d= $grow[3]->v;
    $auto=      $row->e= $grow[4]->v;
    $manual=    $row->f= $grow[5]->v=='0' ? 'O' : $grow[5]->v;
    $oprava=    $row->g= $grow[6]->v;
    $filler=    $row->h= $grow[7]->v;
    $pozn=      $row->i= $grow[8]->v;
//                                         debug($goo->rows[$i],"dar=$dar_jmeno");
//                                         debug($row,"dar=$dar_jmeno");
    // transformace do $tab[]=$row
    $jmeno= substr($dar_jmeno,0,6)=='dar - ' ? substr($dar_jmeno,6) : 
      ( substr($dar_jmeno,0,4)=='dar ' ? substr($dar_jmeno,4) : $dar_jmeno);
    list($prijmeni,$jmeno)= explode(' ',$jmeno);
//                                         display("'$jmeno' '$prijmeni'");
    $opakovane= $jmeno_prvni["$prijmeni$jmeno"] ?: 0;
    if ( !$datum ) break;
    // zapiš opakujícímu se dárci odkaz na řádek s jeho prvním darem
    if ( !$opakovane ) {
      $jmeno_prvni["$prijmeni$jmeno"]= $i+1;
    }
    if ( !$ref && $opakovane ) {
//       $updatedCell= $google->service->updateCell($i,4,$opakovane,$google->sskey,$google->wskey);
      $row->d= $opakovane;
    }
    // doplnění intranetové tabulky a střádání darů do tabulky $darce
    $ido= 0;
    if ( !$auto && !$manual && !$opakovane ) {
      // pokusíme se nalézt dárce
      $idss= array();
      $ids= '';
      $qo= pdo_qry("
        SELECT id_osoba FROM osoba AS o
        WHERE jmeno='$jmeno' AND prijmeni='$prijmeni' AND deleted='' AND access&1
          AND IF(narozeni!='0000-00-00',YEAR(narozeni)<$let18,1)
      ");
      while ($qo && ($o= pdo_fetch_object($qo))) {
        $ido= $o->id_osoba;
        $idss[]= tisk2_ukaz_osobu($o->id_osoba);
      }
      if ( count($idss) ) {
        $ids= implode(', ',$idss);
//                                         display("$jmeno $prijmeni = $ids");
        // zápis do auto
//         $updatedCell= $google->service->updateCell($i,5,$ids,$google->sskey,$google->wskey);
        $row->e= $ids;
        if ( count($idss)==1 ) {
          $jmeno_id["$prijmeni$jmeno"]= $ids;
        }
        else {
          $ido= 0;
          $prblm1.= ($prblm1?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka ($ids)";
        }
      }
      else {
        $prblm2.= ($prblm2?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka";
      }
    }
    elseif ( strpos($auto,',') && !$manual && !$ref ) {
      $prblm1.= ($prblm1?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka ($auto)";
    }
    elseif ( $manual ) {
      if ( $manual=='O' )
        $prblm3.=  ($prblm3?"<br>":'')."0 $dar_jmeno $castka";
      else {
        $jmeno_id["$prijmeni$jmeno"]= tisk2_ukaz_osobu($manual);
        $ido= $manual;
      }
    }
    elseif ( $auto && strpos($auto,',')===false ) {
      $jmeno_id["$prijmeni$jmeno"]= tisk2_ukaz_osobu($auto);
      $ido= $auto;
    }
    // střádání darů od jednoznačně určeného dárce
    $id= $jmeno_id["$prijmeni$jmeno"];
    if ( $id && $castka ) {
      if ( !isset($darce[$id]) ) {
        $darce[$id]= (object)array('data'=>array(),'castka'=>0,'jmeno'=>"$prijmeni $jmeno",'ido'=>$ido);
        $id1= tisk2_ukaz_osobu($ido);
        if ( $ido && $id!=$id1 ) fce_error("ucet_potv: chyba indexu $id!=$id1");
      }
      list($d,$m,$y)= preg_split("/[\/\.]/",$datum);
      $m= 0+$m; $d= 0+$d;
      $darce[$id]->data[]= "$d. $m.";
      $darce[$id]->castka+= $castka;
    }
    $clmn[]= $row;
  }
                                        ksort($jmeno_id); 
//                                        debug($jmeno_id,'jmeno_id');
//                                        debug($darce,'dárce');
//                                         debug($clmn,'clmn');
  // -------------------- vytvoření tabulky pro zobrazení a tisk
  $tab= (object)array(
    'tits'=>explode(',',"datum:10:d,dárce:20,částka,stejný jako ř.:7,ID auto:15,ID ručně,"
                      . "oprava,zapsán,účetnictví:17"),
    'flds'=>explode(',',"a,b,c,d,e,f,g,h,i"),
    'clmn'=>$clmn
  );
  $html.= sta2_table($tab->tits,$tab->flds,$clmn,0,2)->html;
  $html.= "<br><br>";
  $html.= sta2_excel_export("Dárci '$rok'",$tab)->html;
  $reseni= "<br><br>doplň v intranetovém sešitu <b>$prijate_dary</b> v listu <b>$rok</b> do sloupce <b>F</b>
            správné osobní číslo dárce (zjistí se v Evidenci), jen do prvního výskytu dárce<br><br>";
  // -------------------- přehledy
  $rucne= $malo= 0;
  $chibi= array();
  $n_clmn= count($clmn);
  foreach ($clmn as $i=>$row) if ( !$row->d ) {
    $rucne+= $row->f ? 1 : 0;
    if ( !$row->e && !$row->f ) {
      $chibi[]= $i+2;
    }
  }
  $n_jmeno_id= count($jmeno_id);
  $n_darce= count($darce);
  foreach ($darce as $d) {
    $malo+= $d->castka<$mez_daru ? 1 : 0;
  }
  $html.= "<h3>Přehled</h3>celkem je
    $n_clmn darů,
    $n_darce/$n_jmeno_id ($vic) dárců (z toho $rucne poznáno ručně),
    $malo dárců dalo méně jak $mez_daru";
  if ( count($chibi) ) $html.= "<h3>Chybně označené řádky</h3> ve sloupci D,E,F musí být aspoň
    jeden údaj - aspoň '0'. Týká se to řádků: ".implode(', ',$chibi);
  if ( $prblm1 ) $html.= "<h3>Nejednoznačná jména v rámci evidence YS</h3>$prblm1$reseni";
  if ( $prblm2 ) $html.= "<h3>Neznámá jména v rámci evidence YS</h3>$prblm2$reseni";
  if ( $prblm3 ) $html.= "<h3>Vynechaná potvrzení (později ručně napsaná)</h3>$prblm3";
  if ( !$prblm1 && !$prblm2 ) $html.= "<h3>Ok</h3>všichni dárci byli jednoznačně identifikováni :-)";

  // -------------------- zápis do tabulky dar, pokud se to chce
  if ( $druh= $par->save ) {
    // smazání záznamů o účetních darech
    query("DELETE FROM dar WHERE YEAR(dat_od)=$rok AND zpusob='u'");
    // zápis zjištěných darů
    $n= 0;
    foreach ($darce as $ido=>$dary) {
      $ido= $dary->ido;
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      $oki= query("INSERT INTO dar (access,id_osoba,ukon,zpusob,castka,dat_od,note,pars)
        VALUES ($access,$ido,'d','u',{$dary->castka},'$rok-12-31','daňové potvrzení','$pars')");
      $n+= $oki ? pdo_affected_rows($oki) : 0;
    }
    $html.= "<br><br>vloženo $n dárců k potvrzování za rok $rok";
  }
  elseif ( $druh= $par->corr ) {
    // oprava záznamů o účetních darech
    $n1= $n2= $n3= $n4= 0;
    foreach ($darce as $id=>$dary) {
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      // zjištění výše zaznamenaného daru
      $castka2= $dary->castka;
      list($id_dar,$castka1)= select("id_dar,castka","dar","id_osoba=$id AND ukon='d' AND zpusob='u'
        AND dat_od='$rok-12-31' AND note='daňové potvrzení'");
      if ( $castka2==$castka1 ) {
        $n1++;
      }
      elseif ( $id_dar && $castka2 >= $mez_daru ) {
        $pars= ezer_json_encode((object)array('data'=>$data,'bylo'=>$castka1));
                                        display("{$dary->jmeno} $castka1 - $castka2");
        $oku= query("UPDATE dar
          SET castka=$castka2, note='2.daňové potvrzení', pars='$pars'
          WHERE id_dar=$id_dar");
        $n2+= $oku ? pdo_affected_rows($oku) : 0;
      }
      elseif ( !$id_dar && $castka2 >= $mez_daru ) {
        $ido= $dary->ido;
        $oki= query("INSERT INTO dar (id_osoba,ukon,zpusob,castka,dat_od,note,pars)
          VALUES ($ido,'d','u',$castka2,'$rok-12-31','2.daňové potvrzení','$pars')");
        $n4+= $oki ? pdo_affected_rows($oki) : 0;
      }
      else {
        $n3++;
      }
    }
    $html.= "<br><br>dárců za rok $rok: přidáno $n4, opraveno $n2, bez opravy $n1, $n3 pod $mez_daru Kč";
  }
end:
  return (object)array('html'=>$html,'href'=>$href);
}
/** ==========================================================================================> FOTO */
# funkce pro práce se seznamem fotek:
#   set=přidání na konec, get=vrácení n-té, del=smazání n-té a vrácení n-1 nebo n+1
# ---------------------------------------------------------------------------------------- foto2_get
# vrátí n-tou fotografii ze seznamu (-1 znamená poslední) spolu s informacemi:
# ret =>
#   html  = kód pro zobrazení miniatury (s href na původní velikost) nebo chybové hlášení
#   left  = pořadí předchozí fotky nebo 0
#   right = pořadí následující fotky nebo 0
# ? out   = seznam s vynecháním
function foto2_get($table,$id,$n,$w,$h) {  trace();
  global $ezer_path_root;
  $ret= (object)array('img'=>'','left'=>0,'right'=>0,'msg'=>'','tit'=>'','nazev'=>'');
  $fotky= '';
  // názvy fotek
  $osobnich= 0;
  if ( $table=='rodina' ) {
    list($fotky,$rodinna)= select('fotka,nazev','rodina',"id_$table=$id");
  }
  elseif ( $table=='osoba' ) {
    $rf= pdo_qry("
      SELECT GROUP_CONCAT(r.fotka) AS fotky,r.nazev,
        o.fotka,o.prijmeni,o.jmeno
      FROM rodina AS r JOIN tvori USING (id_rodina) JOIN osoba AS o USING (id_osoba)
      WHERE id_osoba=$id AND r.fotka!='' AND role IN ('a','b') ");
    $x= pdo_fetch_object($rf);
    $rodinna= $x->nazev;
    $fotky= $x->fotky;
    $fotka= $x->fotka;
    if ( $fotka ) {
      $osobnich= substr_count($fotka,',')+1;
      $osobni= "{$x->prijmeni} {$x->jmeno}";
      $fotky= $fotky ? "$fotka,$fotky" : $fotka;
    }
  }
  if ( $fotky=='' ) {
    $ret->html= "žádná fotka";
    $ret->jmeno= '';
    goto end;
  }
  $nazvy= explode(',',$fotky);
//            debug($nazvy,"rodina $rodinna");
  // název n-té fotky
  $n= $n==-1 ? count($nazvy) : $n;
//                                         display("fotky='$fotky', n=$n");
  if ( !(1<=$n && $n<=count($nazvy)) ) { $ret->html= "$n je chybné pořadí fotky"; goto end; }
  // výpočet left, right, out
  $ret->left= $n-1;
  $ret->right= $n >= count($nazvy) ? 0 : $n+1;
  // zpracování
  $nazev= $nazvy[$n-1];
  $orig= "$ezer_path_root/fotky/$nazev";
//                                         display("file_exists($orig)=".file_exists($orig));
  if ( !file_exists($orig) ) {
    $ret->html= "fotka <b>$nazev</b> není dostupná";
    goto end;
  }
  // zmenšení na požadovanou velikost, pokud již není
  $dest= "$ezer_path_root/fotky/copy/$nazev";
  if ( !file_exists($dest) ) {
    $ok= foto2_resample($orig,$dest,$w,$h,0,1);
    if ( !$ok ) { $ret->html= "fotka </b>$nazev</b> nešla zmenšit ($ok)"; goto end; }
  }
  // html-kód s žádostí o zaostření na straně klienta
  $ret->nazev= $nazev;
  $jmeno= $n>$osobnich ? $rodinna : $osobni;
  $ret->jmeno= "<span style='font-weight:bold;font-size:120%'>$jmeno</span>";
//                                                 display("$n>$osobnich ? $rodinna : $osobni");
  $stamp= "?x=".time();
  $ret->html= "<a href='fotky/$nazev' target='_album' title='$jmeno ($nazev)'>
    <img src='fotky/copy/$nazev$stamp'
      onload='var x=arguments[0];img_filter(x.target,\"sharpen\",0.7,1);'/></a>";
end:
//                                                 debug($ret,"album_get2($table,$id,$n,$w,$h)");
  return $ret;
}
# ---------------------------------------------------------------------------------------- foto2_add
# přidá fotografii do seznamu (rodina|osoba) podle ID na konec a vrátí její index
# vrátí 0, pokud fotka s tímto jménem již existuje
function foto2_add($table,$id,$name) { trace();
  // přidání názvu fotky do záznamu v tabulce
  $n= 0;
  $f= trim(select('fotka',$table,"id_$table=$id"));
  $fotky= explode(',',$f);
  // vrátí 0, pokud fotka s tímto jménem již existuje
  if (in_array($name,$fotky)) return $n;
  // jinak fotku přidej
  if ($f) $fotky[]= $name; else $fotky= array($name);
  $n= count($fotky);
  $fotky= implode(',',$fotky);
  query("UPDATE $table SET fotka='$fotky' WHERE id_$table=$id");
  return $n;
}
# ------------------------------------------------------------------------------------- foto2_delete
# zruší n-tou fotografii ze seznamu v albu a vrátí pořadí následující nebo předchozí nebo 0
function foto2_delete($table,$id,$n) { trace();
  global $ezer_path_root;
  $ret= (object)array('ok'=>0,'n'=>0);
  // nalezení seznamu názvů fotek
  $fotky= explode(',',select('fotka',$table,"id_$table=$id"));
  if ( 1<=$n && $n<=count($fotky) ) {
    $nazev= $fotky[$n-1];
    unset($fotky[$n-1]);
    $nazvy= implode(',',$fotky);
    query("UPDATE $table SET fotka='$nazvy' WHERE id_$table=$id");
    // smazání fotky a miniatury
    $ret->ok= unlink("$ezer_path_root/fotky/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/$name')=$ok");
    $ret->ok&= unlink("$ezer_path_root/fotky/copy/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/copy/$name')=$ok");
  }
  // vrať nějakou nesmazanou nebo 0
  $ret->n= $n>1 ? $n-1 : (count($fotky) ? 1 : 0);
  return $ret;
}
# ------------------------------------------------------------------------------------- foto2 rotate
# otočení obrázku, deg=90|180|270
function foto2_rotate($nazev,$deg) {
  global $ezer_path_root;
  $src= "$ezer_path_root/fotky/$nazev";
  $ok= foto2_rotate_abs($src,$deg);
  if ( !$ok ) goto end;
  $src= "$ezer_path_root/fotky/copy/$nazev";
  $ok= foto2_rotate_abs($src,$deg);
end:  
  return $ok;
}
# ------------------------------------------------------------------------------------- foto2 rotate
# otočení obrázku, deg=90|180|270
function foto2_rotate_abs($src,$deg) { trace();
  $err= '';
  $ok= 0;
  $part= pathinfo($src);
  $ext= strtolower($part['extension']);
//  $ysrc= "{$part['dirname']}/{$part['filename']}_$deg.$ext";
  $ysrc= $src; // inplace
  ini_set('memory_limit', '512M');
  switch ($ext) {
  case 'jpg':
    if ( !file_exists($src) )  { $err= "$src nelze nalezt"; goto end; }
    $img= imagecreatefromjpeg($src);
    if ( !$img ) { $err= "$src nema format JPEG"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagejpeg($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  case 'png':
    $img= imagecreatefrompng($src);
    if ( !$img ) { $err= "$src nema format PNG"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagepng($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  case 'gif':
    $img= imagecreatefromgif($src);
    if ( !$img ) { $err= "$src nema format GIF"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagegif($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  default:
    $err= "neznamy typ obrazku '$src'";
  }
end:
  if ( $err ) {
    fce_warning($err); 
    $ok= 0;
  }
  return $ok;
}
# ----------------------------------------------------------------------------------- foto2_resample
function foto2_resample($source, $dest, &$width, &$height,$copy_bigger=0,$copy_smaller=1) { #trace();
  global $CONST;
  $maxWidth= $width;
  $maxHeight= $height;
  $ok= 1;
  // zjistime puvodni velikost obrazku a jeho typ: 1 = GIF, 2 = JPG, 3 = PNG
  list($origWidth, $origHeight, $type)=@ getimagesize($source);
//                                                 debug(array($origWidth, $origHeight, $type),"album_resample($source, $dest, &$width, &$height,$copy_bigger)");
  if ( !$type ) $ok= 0;
  if ( $ok ) {
    if ( !$maxWidth ) $maxWidth= $origWidth;
    if ( !$maxHeight ) $maxHeight= $origHeight;
    // nyni vypocitam pomer změny
    $pw= $maxWidth / $origWidth;
    $ph= $maxHeight / $origHeight;
    $p= min($pw, $ph);
    // vypocitame vysku a sirku změněného obrazku - vrátíme ji do výstupních parametrů
    $newWidth = (int)($origWidth * $p);
    $newHeight = (int)($origHeight * $p);
    $width= $newWidth;
    $height= $newHeight;
//                                                 display("p=$p, copy_smaller=$copy_smaller");
    if ( ($pw == 1 && $ph == 1) || ($copy_bigger && $p<1) || ($copy_smaller && $p>1) ) {
//                                                 display("kopie");
      // jenom zkopírujeme
      copy($source,$dest);
    }
    else {
//                                                 display("úprava");
      // zjistíme velikost cíle - abychom nedělali zbytečnou práci
      $destWidth= $destHeight= -1; $ok= 2; // ok=2 -- nic se nedělalo
      if ( file_exists($dest) ) list($destWidth, $destHeight)= getimagesize($dest);
      if ( $destWidth!=$newWidth || $destHeight!=$newHeight ) {
        // vytvorime novy obrazek pozadovane vysky a sirky
        $image_p= ImageCreateTrueColor($newWidth, $newHeight);
        // otevreme puvodni obrazek se souboru
        switch ($type) {
        case 1: $image= ImageCreateFromGif($source); break;
        case 2: $image= ImageCreateFromJpeg($source); break;
        case 3: $image= ImageCreateFromPng($source); break;
        }
        // okopirujeme zmenseny puvodni obrazek do noveho
        if ( $maxWidth || $maxHeight )
          ImageCopyResampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        else
          $image_p= $image;
        // ulozime
        $ok= 0;
        switch ($type) {
        case 1: /*ImageColorTransparent($image_p);*/ $ok= ImageGif($image_p, $dest);  break;
        case 2: $ok= ImageJpeg($image_p, $dest);  break;
        case 3: $ok= ImagePng($image_p, $dest);  break;
        }
      }
    }
  }
  return $ok;
}
/** =========================================================================================> DATA2 **/
# -------------------------------------------------------------------------------------- data_update
# provede změny v dané tabulce pro dané položky a naplní tabulku _track informací o změně
#   $chngs = val1:fld11,fld12,...;val2:...
function data_update ($tab,$id_tab,$chngs) { trace();
  global $USER;
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $updated= 0;
  foreach (explode(';',$chngs) as $val_flds) {
    list($val,$flds)= explode(':',$val_flds);
    foreach (explode(',',$flds) as $fld) {
      $old= select($fld,$tab,"id_{$tab}=$id_tab");
      if ( $old!=$val ) {
        $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                    VALUES ('$now','$user','$tab',$id_tab,'$fld','U','$old','$val')");
        if ( !$ok ) goto end;
        $ok= query("UPDATE $tab SET $fld='$val' WHERE id_$tab=$id_tab");
        $updated+= $ok ? 1 : 0;
      }
    }
  }
  goto end;
err: fce_error("ERROR IN: data_update ($tab,$id_tab,$chngs)");
end: return $updated;
}
/** ========================================================================================> SYSTEM **/
# ---------------------------------------------------------------------------==> . datové statistiky
# ----------------------------------------------------------------------------------------- db2 info
# par.cmd=show - vypíše počet záznamů v důležitých tabulkách a zkotroluje $access pokud je $org!=0
# par.cmd=save - uloží anonymizované údaje do ezer_answer.db_osoba
function db2_info($par) {
  global $ezer_root,$ezer_db,$USER;
  $org= isset($par->org) ? $par->org : 0;
  // přehled tabulek
  $tabs= array(
    'akce'   => (object)array('cond'=>"1",'access'=>$org),
    'cenik'  => (object)array('cond'=>"deleted=''",'access'=>0),
    'rodina' => (object)array('cond'=>"1",'access'=>$org),
    'tvori'  => (object)array('cond'=>"1",'access'=>0),
    'osoba'  => (object)array('cond'=>"deleted=''",'access'=>$org),
    'spolu'  => (object)array('cond'=>"1",'access'=>0),
    'pobyt'  => (object)array('cond'=>"1",'access'=>0),
    'dopis'  => (object)array('cond'=>"1",'access'=>$org),
    'mailist'=> (object)array('cond'=>"1",'access'=>$org),
    'mail'   => (object)array('cond'=>"1",'access'=>0),
    '_user'  => (object)array('cond'=>"deleted=''",'access'=>$org)
   );
  $html= '';
  $db= select('DATABASE()','DUAL',1);
  $tdr= "td style='text-align:right'";
  switch ($par->cmd) {
    case 'show':
      // přehled tabulek podle access
      $html= "<h3>Přehled tabulek $db</h3>";
      $html.= $org ? 
          "<p>POZOR: pokud je v druhém sloupci <b style='background:lightpink'>červený údaj</b>, 
            jedná se chybu, kterou je třeba řešit</p>" : '';
      $html.= "<div class='stat'><table class='stat'>";
      $th= $org ? '<th>skryté?</th>' : '';
      $html.= "<tr><th>tabulka</th><th>záznamů</th>$th</tr>";
      foreach ($tabs as $tab=>$desc) {
        $tab_= strtoupper($tab);
        if ($desc->access) {
          list($pocet,$access)= select("COUNT(*),SUM(IF(access&$org,0,1))","$db.$tab",$desc->cond);
          $red= $access ? ";background:lightpink" : '';
          $td= $org ? "<td style='text-align:right$red'>$access</td>" : '';
        }
        else {
          $pocet= select("COUNT(*)","$db.$tab",1);
          $td= $org ? '<td></td>' : '';
        }
        $html.= "<tr><th>$tab_</th><td style='text-align:right'>$pocet</td>$td</tr>";
      }
      $html.= "</table></div>";
      // úplnost osobních a rodinných údajů
      list($pocet,$tlf,$eml,$nar,$geo1,$geo_)= select("
          COUNT(*),SUM(IF(kontakt=1 AND telefon,1,0)),SUM(IF(kontakt=1 AND email!='',1,0)),
          SUM(IF(narozeni!='0000-00-00',1,0)), 
          SUM(IF(IFNULL(stav,0)=1,1,0)), SUM(IF(IFNULL(stav,0)<0,1,0))
        ",'osoba LEFT JOIN osoba_geo USING (id_osoba)',"deleted=''"); 
      list($r_pocet,$r_geo1,$r_geo_)= select("
          COUNT(*),
          SUM(IF(IFNULL(stav,0)=1,1,0)), SUM(IF(IFNULL(stav,0)<0,1,0))
        ",'rodina LEFT JOIN rodina_geo USING (id_rodina)',"deleted=''"); 
      $dtlf= db2_info_dupl('osoba','telefon',"deleted='' AND kontakt=1 AND telefon");
      $deml= db2_info_dupl('osoba','UPPER(TRIM(email))',"deleted='' AND kontakt=1 AND email!=''");
      $xeml= db2_info_dupl('osoba','MD5(UPPER(TRIM(email)))',"deleted='' AND kontakt=1 AND email!=''");
      $html.= "<h3>Úplnost rodinných a osobních údajů</h3>";
      $html.= "<div class='stat'><table class='stat'>";
      $html.= "<tr><th>tabulka</th><th>záznamů</th><th>geolokace</th>
        <th>telefon</th><th>... dupl</th><th>email</th><th>... dupl</th><th>... kód</th></tr>";
      $html.= "<tr><th>RODINA</th><$tdr>$r_pocet</td><$tdr>$r_geo1 / $r_geo_</td>
        <$tdr>-</td><$tdr>-</td><$tdr>-</td><$tdr>-</td><$tdr>-</td></tr>";
      $html.= "<tr><th>OSOBA</th><$tdr>$pocet</td><$tdr>$geo1 / $geo_</td>
        <$tdr>$tlf</td><$tdr>$dtlf</td><$tdr>$eml</td><$tdr>$deml</td><$tdr>$xeml</td></tr>";
      $html.= "</table></div>";
      // záznamy v jiných databázích
      $tit=   $org==8 
          ? array('8'=>'jen ŠM','3,8'=>'také YMCA','4,8'=>'také CPR','3,4,8'=>'YMCA i CPR') : (
              $org==4 
          ? array('4'=>'jen CPR','3,4'=>'také YMCA','4,8'=>'také ŠM','3,4,8'=>'YMCA i ŠM') : (
              $org==3 
          ? array('3'=>'jen YMCA','3,4'=>'také CPR','3,8'=>'také ŠM','3,4,8'=>'CPR i ŠM') : null));
      $itits= array_keys($tit);
      $val= array('dupl'=>0);
      $rdb= pdo_qry("
        SELECT COUNT(*),_join FROM (
          SELECT COUNT(*) AS _pocet,md5_osoba,GROUP_CONCAT(db ORDER BY db) AS _join
          FROM ezer_answer.db_osoba
          GROUP BY md5_osoba 
          ORDER BY _join
        ) AS _x
        WHERE FIND_IN_SET($org,_join)
        GROUP BY _join
        ORDER BY _join");
      while ($rdb && list($n,$jn)=pdo_fetch_row($rdb)) {
        if (isset($tit[$jn]))
          $val[$jn]= $n;
        else 
          $val['dupl']+= $n;
      }
//      debug($itits);
//      debug($val);
      $ths= $tds= '';
      foreach ($tit as $itit=>$t) {
        $n= $val[$itit]?:0;
        $ths.= "<th>$t</th>";
        $tds.= "<$tdr>$n</td>";
      }
      $ths.= "<th>duplicity</th>";
      $tds.= "<$tdr>{$val['dupl']}</td>";
      $html.= "<h3>Záznamy osob v jiných databázích</h3>
        <div class='stat'><table class='stat'><tr>$ths</tr><tr>$tds</tr></table></div>
        <br><b>Poznámka</b>: Týká se to jen záznamů s vyplněnou emailovou adresou, 
        <br>jejíž MD5 je použito jako anonymizovaný identifikátor osoby";      
      break;
    // úschova anonymizovaných údajů do ezer_answer.db_osoba 
    case 'save': 
      query("DELETE FROM ezer_answer.db_osoba WHERE db=$org");
      query("INSERT INTO ezer_answer.db_osoba(md5_osoba,db,lk_first,lk_last) 
        SELECT MD5(REGEXP_SUBSTR(UPPER(TRIM(email)),'^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]+')),$org,MIN(datum_od),MAX(datum_od)
          FROM $db.osoba  
          LEFT JOIN $db.spolu USING (id_osoba)
          LEFT JOIN $db.pobyt USING (id_pobyt)
          LEFT JOIN $db.akce ON id_akce=id_duakce
          WHERE deleted='' AND kontakt=1 AND email!='' AND spec=0 AND druh=1
          GROUP BY id_osoba");
      $n= select('COUNT(*)','ezer_answer.db_osoba',"db=$org");
      $html.= "Uloženy anonymizované údaje pro $n osob ";
      break;
  }
  return $html;
}
// zjistí počet duplicitních hodnot v dané položce 
function db2_info_dupl($tab,$fld,$cond) {
  $dup= pdo_qry("SELECT COUNT(*) FROM (
      SELECT COUNT(*) AS _pocet
        FROM $tab WHERE deleted='' AND $cond
      GROUP BY $fld HAVING _pocet>1
    ) AS _dupl");
  list($dup)= pdo_fetch_row($dup);
  return $dup;
}
# ----------------------------------------------------------------------------------------- db2 stav
function db2_stav($db) {
  global $ezer_root,$ezer_db,$USER;
  $tabs= array(
    '_user'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'rodina' => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'osoba'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1)
  );
  $html= '';
  // přehled tabulek podle access
  $html= "<h3>Seznam tabulek s rozdělením podle příslušnosti k organizacím</h3>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>tabulka</th>
    <th style='background-color:#f77'>access=0</th>
    <th style='background-color:#af8'>Setkání</th>
    <th style='background-color:#acf'>Familia</th>
    <th style='background-color:#aff'>sdíleno</th>
    <th style='background-color:#aaa'>smazáno</th></tr>";
  foreach ($tabs as $tab=>$desc) {
    $html.= "<tr><th>$tab</th>";
    $obe= 0;
    $rt= pdo_qry("
      SELECT access,COUNT(*) AS _pocet FROM ezer_$db.$tab
      WHERE access=0 AND {$desc->cond} GROUP BY access ORDER BY access");
    if ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right' title='{$t->access}'>{$t->_pocet}</td>";
    }
    else {
      $html.= "<td style='text-align:right' title='0'>0</td>";
    }
    $rt= pdo_qry("
      SELECT access,COUNT(*) AS _pocet FROM ezer_$db.$tab
      WHERE access>0 AND {$desc->cond} GROUP BY access ORDER BY access");
    while ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right' title='{$t->access}'>{$t->_pocet}</td>";
      if ( $t->access==3 ) $obe= 1;
    }
    if ( !$desc->obe ) {
      $html.= "<td style='text-align:right' title='nemá smysl'>-</td>";
    }
    elseif ( !$obe ) {
      $html.= "<td style='text-align:right' title='3'>0</td>";
    }
    $rt= pdo_qry("
      SELECT COUNT(*) AS _pocet FROM ezer_$db._track
      WHERE op='x' AND kde='$tab' AND old='smazaná kopie' ");
    if ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right'>{$t->_pocet}</td>";
    }
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  $vidi= array('ZMI','GAN','HAN');
  if ( in_array($USER->abbr,$vidi) ) {
    $html.= "<br><hr><h3>Sjednocování podrobněji (informace pro ".implode(',',$vidi).")</h3>";
//     $html.= db2_stav_kdo($db,"kdy > '2015-12-01'",
//       "Od prosince 2015 - (převážně) sjednocování Setkání & Familia");
    $html.= db2_prubeh_kdo($db,'2015-11',
      "Sjednocování Setkání & Familia - od teď do prosince 2015");
    $html.= db2_stav_kdo($db,"kdy <= '2015-12-01'",
      "<br><br>... a do prosince 2015 - sjednocení v oddělených databázích");
  }
  // technický stav
  $dbs= array();
  foreach ($ezer_db as $db=>$desc) {
    $dbs[$db]= $desc[5];
  }
  $stav= array(
    "ezer_root"=>$ezer_root,
    "dbs"=>$dbs
  );
//                                        debug($stav);
  return $html;
}
function db2_prubeh_kdo($db,$od,$tit) {
  // sjednotitelé - seznam
  $kdos= $kolik= array();
  $rt= pdo_qry("
    SELECT kdo,SUM(IF(kde IN ('osoba','rodina'),IF(op='d',1,-1),0)) AS _osob
    FROM ezer_$db._track WHERE op IN ('d','V') AND kdy>'$od'
    GROUP BY kdo ORDER BY _osob DESC
  ");
  while ( $rt && (list($kdo,$celkem)= pdo_fetch_row($rt)) ) {
    $kdos[]= $kdo;
    $kolik[$kdo]= $celkem;
  }
  // sjednotitelé - výpočet
  $sje= $mes= array();
  $rt= pdo_qry("
    SELECT kdo,LEFT(kdy,7) as _ym,
      SUM(IF(kde='osoba',IF(op='d',1,-1),0)) AS _osob,
      SUM(IF(kde='rodina',IF(op='d',1,-1),0)) AS _rodin
    FROM ezer_$db._track WHERE op IN ('d','V') AND kdy>'$od'
    GROUP BY kdo,_ym ORDER BY _ym ASC
  ");
  while ( $rt && (list($kdo,$kdy,$osob,$rodin)= pdo_fetch_row($rt)) ) {
    $sje[$kdy][$kdo]= "$osob ($rodin)";
    $mes[$kdy]+= $osob + $rodin;
  }
  // maxima :-)
  $kdys= array_keys($sje);
  $maxi= array();
  foreach ($kdys as $kdy) {
    $max= $maxi[$kdy]= -100;
    foreach ($kdos as $kdo) {
      list($o,$r)= explode(' / ',$sje[$kdy][$kdo]);
      if ( $o+$r > $max ) {
        $max= $o+$r;
        $maxi[$kdy]= $kdo;
      }
    }
  }
  // čas
  $do= date("Y-m");
  foreach ($kdos as $kdo) {
    $grf= "<tr><td style='border:0'></td>";
    $top= "<tr><th>osob (rodin)</th>";
    $row.= "<tr><th>$kdo</th>";
    for ($y=substr($do,0,4); $y>= 2015; $y--) {
      for ($m= 12; $m>=1; $m--) {
        $ym= "$y-".str_pad($m,2,'0',STR_PAD_LEFT);
        if ( $od<$ym && $ym<=$do ) {
          $styl= $maxi[$ym]==$kdo ? " style='background-color:yellow'" : '';
          $h= $mes[$ym] / 5;
          $g= "<div class='curr_akce' style='height:{$h}px;width:30px;'>";
          $grf.= "<td style='vertical-align:bottom;border:0'>$g</td>";
          $top.= "<th>$y.$m</th>";
          $row.= "<td align='right'$styl>{$sje[$ym][$kdo]}</td>";
        }
      }
    }
    $row.= "</tr>";
    $top.= "</tr>";
    $grf.= "</tr>";
  }
  $html.= "$tit<br><br>";
  $html.= "<div class='stat'><table class='stat'>$grf$top$row</table></div>";
  return $html;
}
function db2_stav_kdo($db,$desc,$tit) {
  // sjednotitelé - výpočet
  $sje= array();
  $rt= pdo_qry("
    SELECT kdo,
      SUM(IF(kde='osoba',IF(op='d',1,-1),0)) AS _osob,
      SUM(IF(kde='rodina',IF(op='d',1,-1),0)) AS _rodin
    FROM ezer_$db._track WHERE op IN ('d','V') AND $desc
    GROUP BY kdo");
  while ( $rt && ($t= pdo_fetch_object($rt)) ) {
    $sje[$t->kdo]['o']+= $t->_osob;
    $sje[$t->kdo]['r']+= $t->_rodin;
  }
  // sjednotitelé - řazení
  uasort($sje,function($a,$b) {
    return $a['o']==$b['o'] ? 0 : ($a['o']<$b['o'] ? 1 : -1);
  });
  // sjednotitelé - zobrazení
  $html= "$tit<br><br>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>kdo</th><th>rodin</th><th>osob</th></tr>";
  foreach ($sje as $s=>$or) {
    $html.= "<tr><th>$s</th>";
    $html.= "<td style='text-align:right'>{$or['r']}</td>";
    $html.= "<td style='text-align:right'>{$or['o']}</td>";
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  return $html;
}
# --------------------------------------------------------------------------------==> . testovací db
# --------------------------------------------------------------------------------- db2 copy_test_db
# zkopíruje důležité tabulky z ezer_$db do ezer_$db_test
# pro $db=db2 zkopíruje také setkani4 do setkani4_test
function db2_copy_test_db($db) {  trace();
  $msg= '';
  query("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
  // tabulka, ze které se kopíruje jen posledních $max záznamů, má před jménem hvězdičku
  $max= 5000;
  $tabs= explode(',',
//     "_user,_skill,"
    "*_touch,*_todo,*_track,*mail,"
  . "_help,_cis,ezer_doc2,"
  . "akce,cenik,pobyt,spolu,osoba,tvori,rodina,"
//a  . "g_akce,join_akce,"
  . "prihlaska,"
  . "dar,uhrada,"
  . "dopis,mailist,"
  . "pdenik,person,pokladna,"
  . "faktura,platba,join_platba"
  );
  $msg.= "<h3>Kopie databáze ezer_{$db} do ezer_{$db}_test</h3>";
  foreach ($tabs as $xtab ) {
    $tab= $xtab;
    if ( $tab[0]=='*' ) $tab= substr($tab,1);
    query("DROP TABLE IF EXISTS ezer_{$db}_test.$tab");
    query("CREATE TABLE ezer_{$db}_test.$tab LIKE ezer_{$db}.$tab");
    $LIMIT= '';
    if ( $xtab[0]=='*' ) {
      $count= select('COUNT(*)',$tab);
      if ($count>$max) $LIMIT= "LIMIT ".($count-$max).", $max";
    }
    $MAX= $xtab[0]=='*' ? "WHERE YEAR(kdy)=YEAR(NOW())" : '';
    $n= query("INSERT INTO ezer_{$db}_test.$tab SELECT * FROM ezer_{$db}.$tab $LIMIT");
    $msg.= "<br>COPY ezer_{$db}_test.$tab ... $n záznamů $LIMIT";
  }
  // kopie pro Dům setkání
  if ($db=='db2') {
    $msg.= "<h3>Kopie databáze setkani4 do setkani4_test</h3>";
    // tabulka¨, která se má jen vytvořit, má před jménem hvězdičku
    $tabs= explode(',',
      "*_touch,*_todo,_track,"
    . "_help,_cis,"
    . "ds_cena,ds_osoba,tx_gnalberice_order,tx_gnalberice_room"
    );
    foreach ($tabs as $xtab ) {
      $tab= $xtab;
      if ( $tab[0]=='*' ) $tab= substr($tab,1);
      query("DROP TABLE IF EXISTS setkani4_test.$tab");
      query("CREATE TABLE setkani4_test.$tab LIKE setkani4.$tab");
      if ( $xtab[0]!='*' ) {
        $n= query("INSERT INTO setkani4_test.$tab SELECT * FROM setkani4.$tab");
        $msg.= "<br>COPY setkani4_test.$tab ... $n záznamů";
      }
      else {
        $msg.= "<br>INIT setkani4_test.$tab";
      }
    }
    // poznámka k VIEW
    $msg.= "<h3>Zůstávají zachovány definice VIEW z databáze ezer_setkani4_test do ezer_db2_test</h3>
      <br>VIEW ds_order
      <br>VIEW objednávka";
  }
  // end
  ezer_connect("ezer_{$db}");   // jinak zůstane přepnuté na test
  return $msg;
}
# -----------------------------------------------------------------------------------==> . track ops
# --------------------------------------------------------------------------------------- track_like
# vrátí změny podobné předané (stejný uživatel, tabulka, čas +-10s)
if(!function_exists("track_like")) {
function track_like($id) {  trace();
  $ret= (object)array('ok'=>1);
  $ids= $id;
  list($kdo,$kde,$kdy,$klic,$op)= select('kdo,kde,kdy,klic,op','_track',"id_track=$id");
  if ( strpos("uU",$op)===false ) {
    $ret->ok= 0;
    $ret->msg= "Bohužel, vrácení úprav typu '$op' není podporováno";
    goto end;
  }
  // nalezení podobných
  $diff= "10 SECOND";
  $xr= pdo_qry(
    "SELECT COUNT(*) AS _pocet,GROUP_CONCAT(id_track) AS _ids
     FROM _track
     WHERE kdo='$kdo' AND kde='$kde' AND klic='$klic' AND op='$op'
       AND kdy BETWEEN DATE_ADD('$kdy',INTERVAL -$diff) AND DATE_ADD('$kdy',INTERVAL $diff)");
  $x= pdo_fetch_object($xr);
  $ret->ids= $x->_ids;
  if ( $x->_pocet > 10 ) {
    $ret->msg= "pozor je příliš mnoho změn - {$x->_pocet}";
    $ret->ok= 0;
  }
end:
  return $ret;
}
}
# ------------------------------------------------------------------------------------- track_revert
# pokusí se vrátit učiněné změny - $ids je seznam id_track
if(!function_exists("track_revert")) {
function track_revert($ids) {  trace();
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $ret= (object)array('ok'=>1);
  $xr= pdo_qry("SELECT * FROM _track WHERE id_track IN ($ids)");
  while ( $xr && ($x= pdo_fetch_object($xr)) ) {
    $table= $x->kde; $id= $x->klic; $op= $x->op; $fld= $x->fld;
    $old= $x->old; $val= $x->val;
    $ret->tab= $table;
    $ret->klic= $id;
    switch ($op ) {
    case 'd': // ------------------------------- vrácení sjednocení
      if ( $table=='osoba' || $table=='rodina' ) {
        $chngs= explode(';',$old); // seznam změn k vrácení pro id_osoba=$val
        foreach ($chngs as $chng) {
          list($fld,$lst)= explode(':',$chng);
          $_id= $fld=='pobyt' ? 'i0_rodina' : (
                $fld=='mail'  ? 'id_clen'   :  "id_$table");
          switch ($fld) {
          case 'access': // -------------------- zápis o vrácení a obnově
            // obnov smazanou osobu nebo rodinu
            query("UPDATE $table SET deleted='' WHERE id_$table=$val");
            query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                   VALUES ('$now','$user','$table',$val,'','o','obnovená kopie','$id')");     // o=obnova
            // zapiš info o vrácení jako V
            query("UPDATE $table SET access=$lst WHERE id_$table=$id");
            query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                   VALUES ('$now','$user','$table',$id,'$table','V','vrácené sjednocení','$val')");  // V=vrácení
            break;
          case 'pobyt':
          case 'mail':
          case 'tvori':
          case 'spolu':
          case 'dar':
//          case 'platba':
            if ( $lst ) {
              query("UPDATE $fld SET $_id=$val WHERE id_$fld IN ($lst)");
            }
            break;
          case 'xtvori':
            list($idr,$role)= explode('.',$lst);
            query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUES ($idr,$val,'$role')");
            break;
          default:
            $ret->ok= 0;
            $ret->msg= "Bohužel, vrácení úprav typu '$op/$fld' není podporováno";
          }
        }
      }
      else {
        $ret->ok= 0;
        $ret->msg= "Bohužel, toto sjednocení již nelze vrátit";
      }
      break;
    case 'u': // ------------------------------- vrácení změny
    case 'U':
      $curr= select($fld,$table,"id_$table=$id");
      if ( $curr==$val )
        ezer_qry("UPDATE",$table,$id,array(
          (object)array('fld'=>$fld, 'op'=>'u','val'=>$old,'old'=>$curr)
        ));
      break;
    default:
      $ret->ok= 0;
      $ret->msg= "Vrácení úprav typu '$op' není podporováno";
    }
  }
  return $ret;
}
}
# =======================================================================> db2 kontrola a oprava dat
# ----------------------------------------------------------------------------------- db2 oprava_dat
/*
# opravy dat
function db2_oprava_dat($par) { trace(); debug($par);
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $opravit= $par->opravit ? true : false;
  $cmd= $par->cmd;
  $html= '';
  $ok= '';
  switch ($cmd) {
  // -------------------------------------------- schema 2014
  case 'kontakty':
  case 'kontakty+':
  case 'adresy':
  case 'adresy+':
    $html.= data2_transform_2014($par);
    break;
  case 'umi':
  // -------------------------------------------- přenesení pobyt.funkce do o_umi r_umi
  //
  // doplní o_umi(L,K,P) r_umi(S,L)
  //   pokud jich je >1
  // podle spolu --> osoba --> tvori --> rodina.nazev,id_rodina
  case 'umi':
    $no= $nr= $nou= $nru= 0;
    $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
    $qp= pdo_qry("
      SELECT id_pobyt,i0_rodina,id_osoba,funkce,COUNT(DISTINCT id_osoba),
        o_umi,IFNULL(r_umi,'')
      FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN rodina AS r ON id_rodina=i0_rodina
      WHERE funkce IN (1,12,3,4) $AND
      GROUP BY id_pobyt
    ");
    while ( $qp && (list($idp,$idr,$ido,$fce,$pocet,$o_umi,$r_umi)= pdo_fetch_array($qp)) ) {
      $o= $r= 0;
//                         display("$idp,$idr,$ido,$fce,$pocet,$o_umi,$r_umi");
      switch ($fce) {
      case 1: // VPS -> r_umi
                                display("strpos($r_umi,$fce)=".strpos($r_umi,$fce));
        if ( strpos($r_umi,$fce)===false ) {
          $r_umi= $r_umi ? "$fce,$r_umi" : $fce;
          $r++;
          $nr++;
        }
//                         display("$nr: $idp,$idr,$ido,$fce,$pocet,$o_umi,$r_umi");
        break;
      case 3: // kněz  -> o_umi
      case 4: // psych -> o_umi
        if ( $pocet==1 && strpos($o_umi,$fce)===false ) {
          $o_umi= $o_umi ? "$fce,$o_umi" : $fce;
          $o++;
          $no++;
        }
        break;
      case 12: // lektor -> o_umi (pro pocet=1) resp. r_umi
        $fce= 2;
        if ( $pocet==1 ) {
          if ( strpos($o_umi,$fce)===false ) {
            $o_umi= $o_umi ? "$fce,$o_umi" : $fce;
            $o++;
            $no++;
          }
        }
        else {
          if ( strpos($r_umi,$fce)===false ) {
            $r_umi= $r_umi ? "$fce,$r_umi" : $fce;
            $r++;
            $nr++;
          }
        }
        break;
      }
      if ( $opravit ) {
        if ( $o ) {
          pdo_qry("UPDATE osoba SET o_umi='$o_umi' WHERE id_osoba=$ido");
          $nou+= pdo_affected_rows();
        }
        if ( $r ) {
          pdo_qry("UPDATE rodina SET r_umi='$r_umi' WHERE id_rodina=$idr");
          $nru+= pdo_affected_rows();
        }
      }
    }
    $html.= "<br>zjištěno $no nových osobních a $nr nových rodinných schopností";
    $html.= "<br>doplněno $nou nových osobních a $nru nových rodinných schopností";
    break;
  // ---------------------------------------------- pobyt: i0_rodina ... do starých
  // doplní i0_rodina pokud rodina má jméno a je jednoznačná pro všechny osoby pobytu
  //   pokud jich je >1
  // podle spolu --> osoba --> tvori --> rodina.nazev,id_rodina
  case 'i0_rodina':
    $n= $nu= 0;
    $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
    $qp= pdo_qry("
      SELECT COUNT(*) AS _ucastniku,COUNT(DISTINCT id_rodina) AS _pocet,id_pobyt,id_rodina
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING (id_pobyt)
      JOIN tvori AS t USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
      WHERE i0_rodina=0 AND r.nazev!='' $AND
      GROUP BY id_pobyt HAVING _ucastniku>1 AND _pocet=1 ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
      $n++;
      if ( $opravit ) {
        pdo_qry("UPDATE pobyt SET i0_rodina={$p->id_rodina} WHERE id_pobyt={$p->id_pobyt}");
        $nu+= pdo_affected_rows();
      }
    }
    $html.= "<br>zjištěno $n x pobyt.i0_rodina=0
      pro pobyty s jednoznačnou rodinou pro všechny osoby pobytu";
    $html.= "<br>doplněno $nu x pobyt.i0_rodina";
    break;
  default:
    $html.= "transformaci $cmd neumím";
  }
  return $html;
}
*/
# --------------------------------------------------------------------------------- db2 kontrola_dat
/*
# kontrola dat
function db2_kontrola_dat($par) { trace();
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $html= '';
  $auto= " <b>LZE OPRAVIT AUTOMATICKY</b>";
  $uziv= " <b>NUTNO OPRAVIT RUČNĚ</b>";
  $n= 0;
  $opravit= $par->opravit ? true : false;
  $msg= '';
  $ok= '';
  // testy nových kontrol
  // ----------------------------------------------==> .. testy
  if ( isset($par->test) ) {
    switch ($par->test) {
    case 'test':
  # -----------------------------------------==> .. spolu vede na smazanou osobu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_spolu,id_osoba,id_pobyt,CONCAT(jmeno,' ',prijmeni),o.deleted,a.nazev
    FROM spolu JOIN osoba AS o USING (id_osoba) JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
    WHERE o.deleted!=''
    ORDER BY id_pobyt
  ");
  while ( $rr && (list($ids,$ido,$idp,$jm,$od,$nazev)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu=$ids",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd pobytu $nazev/$idp je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: vazba na smazané osoby"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
      break;
    }
    goto end;
  }
//   goto access;
  // kontrola nenulovosti klíčů ve spojovacích záznamech
  // ----------------------------------------------==> .. nulové klíče ve SPOLU
  $cond= "id_pobyt=0 OR spolu.id_osoba=0 ";
  $qry=  "SELECT id_spolu,spolu.id_osoba,spolu.id_pobyt,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=spolu.id_osoba
          WHERE $cond";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= pdo_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam spolu={$x->id_spolu} je nulový$ok</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu spolu={$x->id_spolu} pobytu={$x->id_pobyt} akce {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>pobyt=0 v záznamu spolu={$x->id_spolu} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'> tabulka <b>spolu</b>: nulové klíče osoby nebo pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. spolu vede na smazanou osobu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_spolu,id_osoba,id_pobyt,CONCAT(jmeno,' ',prijmeni),o.deleted,
      CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev
    FROM spolu JOIN osoba AS o USING (id_osoba) JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
    WHERE o.deleted!=''
    ORDER BY id_pobyt
  ");
  while ( $rr && (list($ids,$ido,$idp,$jm,$od,$nazev)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu=$ids",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd pobytu $nazev/$idp je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: vazba na smazané osoby"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ----------------------------------------------==> .. násobné SPOLU
  $msg= '';
  $qry=  "SELECT GROUP_CONCAT(id_spolu) AS _ss,id_pobyt,s.id_osoba,count(*) AS _pocet_,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_pobyt HAVING _pocet_>1
          ORDER BY id_akce";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ss= explode(',',$x->_ss);
      unset($ss[0]);
      $ss= implode(',',$ss);
      if ( count($ss) ) {
        $ok= pdo_qry("DELETE FROM spolu WHERE id_spolu IN ($ss)")
          ? (" = spolu SMAZÁNO ".pdo_affected_rows($ok).'x') : ' CHYBA při mazání spolu' ;
      }
    }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $msg.= "<dd>násobný pobyt záznamy spolu={$x->_ss} na akci <b>{$x->nazev}</b>
      osoby $ido:{$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: zdvojení osoby ve stejném pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ---------------------------------------==> .. zdvojení osoby v různých pobytech na stejné akci
  $msg= '';
  $qry=  "SELECT s.id_osoba,GROUP_CONCAT(id_pobyt) AS _sp,count(DISTINCT id_pobyt) AS _pocet_,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,id_akce,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          -- WHERE platba+platba_d=0 AND funkce!=99
          GROUP BY id_osoba,id_akce HAVING _pocet_>1
          ORDER BY id_akce";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $pp= $del= '';
    $ida= $x->id_akce;
    foreach (explode(',',$x->_sp) as $idp) {
      $pp.= $del.tisk2_ukaz_pobyt_akce($idp,$ida);
      $del= ", ";
    }
    $msg.= "<dd>násobný pobyt na akci {$x->nazev} - pobyty $pp pro
      osobu $ido:{$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: zdvojení osoby v různých pobytech na stejné akci"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ---------------------------------------==> .. nulové hodnoty v tabulce TVORI
tvori:
  $msg= '';
  $cond= "tvori.id_rodina=0 OR tvori.id_osoba=0 OR ISNULL(o.id_osoba) OR ISNULL(r.id_rodina)";
  $qry=  "SELECT id_tvori,role,tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno,
             IFNULL(o.id_osoba,0) AS _o_ido, IFNULL(r.id_rodina,0) AS _r_idr
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          WHERE $cond ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
//     if ( $opravit ) {
//       $ok= pdo_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
//          ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
//     }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam tvori={$x->id_tvori} je nulový</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu tvori={$x->id_tvori} rodiny={$x->id_rodina} {$x->nazev}$ok</dd>";
    if ( !$x->id_rodina )
      $msg.= "<dd>rodina=0 v záznamu tvori={$x->id_tvori} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
    if ( !$x->_o_ido ) {
      $idr= tisk2_ukaz_rodinu($x->id_rodina);
      $track= db2_track_osoba($x->id_osoba);
      $idt= db2_smaz_tvori($x->id_tvori);
      $msg.= "<dd>neexistující osoba id=$track v záznamu tvori=$idt
              rodiny $idr:{$x->nazev} $ok</dd>";
    }
    if ( !$x->_r_idr ) {
      $ido= tisk2_ukaz_osobu($x->id_osoba);
      $track= db2_track_rodina($x->id_rodina);
      $idt= db2_smaz_tvori($x->id_tvori);
      $msg.= "<dd>neexistující rodina id=$track v záznamu tvori=$idt
              osoby $ido:{$x->prijmeni} {$x->jmeno}$ok</dd>";
    }
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: nulové a neexistující hodnoty v tabulce"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
// goto end;
  # -----------------------------------------==> .. tvori vede na smazanou osobu/rodinu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_tvori,id_osoba,id_rodina,r.nazev,CONCAT(jmeno,' ',prijmeni),o.deleted,r.deleted
    FROM tvori JOIN osoba AS o USING (id_osoba) JOIN rodina AS r USING (id_rodina)
    WHERE o.deleted!='' OR r.deleted!=''
    ORDER BY id_rodina
  ");
  while ( $rr && (list($idt,$ido,$idr,$nazev,$jm,$od,$rd)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    $srd= $rd ? "smazané" : '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM tvori WHERE id_tvori=$idt",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd rodině $nazev/$idr je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: vazby mezi smazanými záznamy"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. násobné členství v rodině
  $msg= '';
  $qry=  "SELECT GROUP_CONCAT(id_tvori) AS _ts,count(*) AS _pocet_,GROUP_CONCAT(DISTINCT role) AS _role_,
            tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          GROUP BY id_osoba,id_rodina HAVING _pocet_>1
          ORDER BY id_rodina ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    $ts= explode(',',$x->_ts);
    if ( $opravit && strlen($x->_role_)==1 ) {
      $ok.= pdo_qry("DELETE FROM tvori WHERE id_tvori={$ts[0]}",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $msg.= "<dd>násobné členství záznamem tvori=({$x->_ts}) v rodině $idr:{$x->nazev}
      osoby $ido:{$x->prijmeni} {$x->jmeno} v roli {$x->_role_}  $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: násobné členství osoby v rodině"
    .($msg?"{$auto} pokud osoba nemá více rolí$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. mnoho otců/matek
  $msg= '';
  $qry=  "SELECT SUM(IF(role='a',1,0)) AS _otcu, GROUP_CONCAT(IF(role='a',id_tvori,'')) AS _otci,
            SUM(IF(role='b',1,0)) AS _matek, GROUP_CONCAT(IF(role='b',id_tvori,'')) AS _matky,
            id_rodina, nazev
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          GROUP BY id_rodina HAVING _otcu>1 OR _matek>1
          ORDER BY id_rodina ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $otci= trim(str_replace(',,',',',$x->_otci),',');
    $matky= trim(str_replace(',,',',',$x->_matky),',');
    if ( $x->_otcu>1 )
      $msg.= "<dd>{$x->_otcu} muži v roli 'a' v rodině $idr:{$x->nazev} ($otci)</dd>";
    if ( $x->_matek>1 )
      $msg.= "<dd>{$x->_matek} ženy v roli 'b' v rodině $idr:{$x->nazev} ($matky)</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: nestandardní počet otců='a', matek='b' v rodině"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
  // -------------------------------------------==> .. fantómová osoba
  $msg= '';
  $rx= pdo_qry("
    SELECT o.id_osoba,id_dar,id_platba,s.id_spolu,p.id_pobyt,a.id_duakce,
      a.nazev,id_tvori,r.id_rodina,r.nazev,t.role 
    FROM osoba AS o
    LEFT JOIN dar    AS d ON d.id_osoba=o.id_osoba
    LEFT JOIN platba AS x ON x.id_osoba=o.id_osoba
    LEFT JOIN spolu  AS s ON s.id_osoba=o.id_osoba
    LEFT JOIN pobyt  AS p ON p.id_pobyt=s.id_pobyt
    LEFT JOIN akce   AS a ON a.id_duakce=p.id_akce
    LEFT JOIN tvori  AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r ON r.id_rodina=t.id_rodina
    WHERE prijmeni='' AND jmeno='' AND o.origin=''
    ORDER BY o.id_osoba DESC
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
//     if ( $opravit ) {
//       $ok= '';
//       if ( !$x->id_dar && !$x->id_platba ) {
//         $ok.= pdo_qry("DELETE FROM spolu WHERE id_osoba={$x->id_osoba}")
//            ? (" = spolu SMAZÁNO ".pdo_affected_rows().'x') : ' CHYBA při mazání spolu' ;
//         $ok.= pdo_qry("DELETE FROM osoba WHERE id_osoba={$x->id_osoba}")
//            ? ", osoba SMAZÁNO " : ' CHYBA při mazání osoba ' ;
//       }
//       else
//         $ok= " = vazba na dar či platbu";
//     }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $msg.= "<dd>fantómová osoba $ido v rodině $idr:{$x->nazev} v roli {$x->role} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>osoba</b>: fantómové osoby"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
  // ---------------------------------------------==> .. triviální RODINA
  $msg= '';
  $rx= pdo_qry("
    SELECT id_rodina,IFNULL(MAX(id_tvori),0) AS _idt,nazev,COUNT(*) AS _pocet
    FROM rodina AS r LEFT JOIN tvori USING (id_rodina)
    LEFT JOIN dar    AS d USING (id_rodina)
    LEFT JOIN platba AS x USING (id_rodina)
    LEFT JOIN pobyt AS p ON r.id_rodina=p.i0_rodina
    WHERE r.deleted=''
    GROUP BY id_rodina HAVING _idt=0 AND _pocet=1
  ");
  while ( $rx && (list($idr,$idts,$nazev)= pdo_fetch_row($rx)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ok= query("UPDATE rodina SET deleted='D' WHERE id_rodina=$idr")
         ? ", SMAZÁNO " : ' CHYBA při mazání rodina ' ;
      query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
             VALUES ('$now','$user','rodina',$idr,'','x','','')");
    }
    $msg.= "<dd>triviální rodina bez závazků $nazev/$idr $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>rodina</b>: rodina bez členů"
    .($msg?"$auto<br>$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. triviální POBYT
  $msg= '';
  $rx= pdo_qry("
    SELECT id_pobyt,nazev,YEAR(datum_od) AS _rok
    FROM pobyt AS p
    LEFT JOIN spolu USING (id_pobyt)
    LEFT JOIN akce AS a ON a.id_duakce=p.id_akce
    -- LEFT JOIN mail USING(id_pobyt)
    WHERE ISNULL(id_spolu) AND funkce!=99
    GROUP BY id_pobyt -- HAVING _pocet=0
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
    if ( $opravit ) { // ... zkontrolovat mail
      $ok= pdo_qry("DELETE FROM pobyt WHERE id_pobyt={$x->id_pobyt}",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $idp= tisk2_ukaz_pobyt($x->id_pobyt);
    $msg.= "<dd>triviální pobyt $idp: rok {$x->_rok} {$x->nazev} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: pobyt bez osob"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. POBYT bez akce
  $msg= '';
  $rx= pdo_qry("
    SELECT id_pobyt,id_spolu,id_akce,jmeno,prijmeni
    FROM pobyt JOIN spolu USING(id_pobyt) JOIN osoba USING(id_osoba)
    WHERE id_akce=0
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu}")
        ? " = spolu SMAZÁNO " : ' CHYBA při mazání spolu' ;
      $ok.= pdo_qry("DELETE FROM pobyt WHERE id_pobyt={$x->id_pobyt}")
        ? ", pobyt SMAZÁNO " : ' CHYBA při mazání pobyt ' ;
    }
    $msg.= "<dd>pobyt bez akce - {$x->pobyt} pro osobu {$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: nulová akce"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. ACCESS=3 ale pobyty tomu neodpovídají
access:
  $msg= $ok= '';
  $osoby_upd= array();
  $rr= pdo_qry("
    SELECT BIT_OR(a.access) AS _aa,r.access,
      GROUP_CONCAT(DISTINCT CONCAT(o.access,':',o.id_osoba)) AS _oas,id_rodina,r.nazev
    FROM rodina AS r JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba) JOIN spolu AS s USING (id_osoba)
    JOIN pobyt AS p USING (id_pobyt) JOIN akce AS a ON id_akce=id_duakce
    WHERE r.access=3
    GROUP BY id_rodina
    HAVING _aa<3
  ");
  while ( $rr && (list($aa,$ra,$oas,$idr,$jm)= pdo_fetch_row($rr) ) ) {
    $n++;
    $osoby_o= $osoby_a= array();
    foreach (explode(',',$oas) as $oa) {
      list($aa1,$oa1)= explode(':',$oa);
      $osoby_o[]= $oa1;
      $osoby_a[]= $aa1;
      $n++;
    }
    if ( $opravit ) {
      ezer_qry("UPDATE","rodina",$idr,array(
        (object)array('fld'=>'access', 'op'=>'U','val'=>$aa,'old'=>$ra)
      ));
      foreach ($osoby_o as $i=>$ido) {
        if ( !in_array($ido,$osoby_upd) ) {
          ezer_qry("UPDATE","osoba",$ido,array(
            (object)array('fld'=>'access', 'op'=>'U','val'=>$aa,'old'=>$osoby_a[$i])
          ));
          $osoby_upd[]= $ido;
        }
      }
      $ok= " OPRAVENO";
    }
    $msg.= "<dd>rodina $jm/$idr jezdí jen na akce $aa i její členové ".implode(', ',$osoby_o)." $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>rodina</b>: je označena jako společná
             ale její členové jezdí býhradně na akce jedné organizace"
        . ($msg?"$auto<br>$msg":"<dd>ok</dd>")."</dt>";

end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# -----------------------------------------------------------------------------==> . db2 track_osoba
# zobrazí odkaz na rodinu v evidenci
function db2_track_osoba($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.track_osoba/$ido'>$ido</a></b>";
}
# ----------------------------------------------------------------------------==> . db2 track_rodina
# zobrazí odkaz na rodinu v evidenci
function db2_track_rodina($idr,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.track_rodina/$idr'>$idr</a></b>";
}
# ------------------------------------------------------------------------------==> . db2 smaz_tvori
# zobrazí odkaz na rodinu v evidenci
function db2_smaz_tvori($idt,$barva='red') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.smaz_tvori/$idt'>$idt</a></b>";
}
*/
/** ========================================================================================> GROUPS **/
# ----------------------------------------------------------------------------------------- grp_read
# par.file = stáhnutý soubor
function grp_read($par) {  trace(); //debug($par);
  mb_internal_encoding("UTF-8");
  $html= $msg= '';
  $r= " align='right'";
  $y= " style='background-color:yellow'";
  $sav= false;
  $max= 4;
  $max= 999999;
  $mesice= array('ledna'=>1,'února'=>2,'března'=>3,'dubna'=>4,'května'=>5,'června'=>6,
                 'července'=>7,'srpna'=>8,'září'=>9,'října'=>10,'listopadu=>11','prosince'=>12);

  switch ($par->meth) {
  # -------------------------------------------------------------------------------------- INFORMACE
  case 'ana':
    list($zprav,$posledni,$prvni)= select("COUNT(*),MAX(datum),MIN(datum)","gg_mbox","1");
    $prvni= sql_date1($prvni);
    $posledni= sql_date1($posledni);
    $html.= "Následné analýzy platí pro $zprav zpráv konference chlapi-iniciace,
             napsaných v období $prvni až $posledni";
    break;

  # ----------------------------------------------------------------------------------- ANA: ANALÝZY
  #
  case 'ana_unknown':   # ------------------------------------------------------- ANA: neznámé
    $html= "<table class='stat'><tr><th>email</th><th>aktivita</th><th>od</th><th>do</th></tr>";
    $rh= pdo_qry("
      SELECT email,zprav,YEAR(prvni),YEAR(posledni)
      FROM gg_osoba WHERE id_osoba=0
      ORDER BY zprav DESC
    ");
    while ( $rh && (list($email,$aktivita,$prvni,$posledni)= pdo_fetch_row($rh)) ) {
      $html.= "<tr><td>$email</td><td>$aktivita</td><td>$prvni</td><td>$posledni</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_activity':  # ------------------------------------------------------- ANA: aktivní
    $mez= 0;
    $html= "<table class='stat'><tr><th>email</th><th>účastník</th><th>zpráv</th><th>iniciace</th>
            <th>od</th><th>do</th></tr>";
    $rh= pdo_qry("
      SELECT LEFT(GROUP_CONCAT(g.email),100),SUM(zprav) AS _zprav,iniciace,
        MIN(YEAR(prvni)),MAX(YEAR(posledni)),jmeno,prijmeni
      FROM gg_osoba AS g
      LEFT JOIN osoba AS o USING (id_osoba)
      GROUP BY IF(id_osoba,id_osoba,g.email) HAVING _zprav>$mez
      ORDER BY _zprav DESC
    ");
    while ( $rh
      && (list($email,$aktivita,$mrop,$prvni,$posledni,$jmeno,$prijmeni)= pdo_fetch_row($rh)) ) {
      $style1= !$mrop
            ? " style='background-color:yellow'" : '';
      $style2= $mrop<2007 && $prvni>2007 || $mrop>=2007 && $prvni!=$mrop
            ? " style='background-color:yellow'" : '';
      $html.= "<tr><td>$jmeno $prijmeni</td><td$style1>$email</td><td>$aktivita</td><td>$mrop</td>
               <td$style2>$prvni</td><td>$posledni</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_vlakna':    # ------------------------------------------------------- ANA: vlákna
    $html= "<table class='stat'><tr><th>rok</th><th>příspěvků</th><th>diskutujících</th>
            <th>předmět</th></tr>";
    $rh= pdo_qry("
      SELECT COUNT(*) AS _pocet,COUNT(DISTINCT email),MIN(YEAR(datum)),MAX(YEAR(datum)),
        LEFT(nazev,50),COUNT(DISTINCT root)
      FROM gg_mbox
      -- WHERE root!=0
      GROUP BY nazev -- root
      ORDER BY _pocet DESC
    ");
    while ( $rh && (list($delka,$lidi,$od,$do,$nazev,$roots)= pdo_fetch_row($rh)) ) {
      $roky= $od.($do!=$od? "-$do" : '');
      $flame= $do-$od<2 && $delka>10 && $delka>1.8*$lidi && $roots==1 ? $y : '';
      $html.= "<tr><td>$roky</td><td>$delka</td><td$flame>$lidi</td><td>$nazev</td>
        <td>$roots</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_roky':      # ------------------------------------------------------- ANA: roky
    $html= "<table class='stat'><tr><th>rok</th><th>diskutujících</th><th>příspěvků</th>
      <th>vláken</th><th>nejdelší</th><th>název vlákna</th><th>vláken II</th></tr>";
    $rh= pdo_qry("
      SELECT YEAR(datum) AS _rok,COUNT(*) AS _pocet,SUM(IF(back=0,1,0)),COUNT(DISTINCT email),
        MAX(CONCAT(LPAD(reakci,4,'0'),nazev)),COUNT(DISTINCT nazev)
      FROM gg_mbox
      GROUP BY _rok
      ORDER BY _rok DESC
    ");
    while ( $rh && (list($rok,$prisp,$vlakna,$lidi,$nazev,$nazvu)= pdo_fetch_row($rh)) ) {
      $max= substr($nazev,0,4)+0;
      $nazev= substr($nazev,4);
      $html.= "<tr><td>$rok</td><td$r>$lidi</td><td$r>$prisp</td><td$r>$vlakna</td>
        <td$r>$max</td><td>$nazev</td><td>$nazvu</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_mesice':    # ------------------------------------------------------- ANA: měsíce
    $old= 0;
    $html= "<table class='stat'><tr><th>rok</th><th>diskutujících</th><th>příspěvků</th><th>vláken</th>
      <th>nejdelší</th><th>název vlákna</th></tr>";
    $rh= pdo_qry("
      SELECT LEFT(datum,7) AS _rok,COUNT(*) AS _pocet,SUM(IF(back=0,1,0)),COUNT(DISTINCT email),
        MAX(CONCAT(LPAD(reakci,4,'0'),nazev))
      FROM gg_mbox
      GROUP BY _rok
      ORDER BY _rok DESC
    ");
    while ( $rh && (list($mesic,$prisp,$vlakna,$lidi,$nazev)= pdo_fetch_row($rh)) ) {
      $rok= substr($mesic,0,4);
      $mesic= substr($mesic,5);
      if ( $rok==$old ) $cas= $mesic;
      else { $old= $rok; $cas= "$rok/$mesic"; }
      $max= substr($nazev,0,4)+0;
      $nazev= substr($nazev,4);
      $html.= "<tr><td>$cas</td><td$r>$lidi</td><td$r>$prisp</td><td$r>$vlakna</td>
        <td$r>$max</td><td>$nazev</td></tr>";
    }
    $html.= "</table>";
    break;

//   case 'ana_lidi_y':    # ------------------------------------------------------- ANA: lidi
//     $html= "<table class='stat'><tr><th>rok</th><th>aktivních účastníků</th></tr>";
//     $rh= pdo_qry("
//       SELECT COUNT(*) AS _pocet,YEAR(prvni) AS _rocnik
//       FROM gg_osoba
//       GROUP BY _rocnik
//       ORDER BY _rocnik DESC
//     ");
//     while ( $rh && (list($_pocet,$_rocnik)= pdo_fetch_row($rh)) ) {
//       $html.= "<tr><td>$_rocnik</td><td>$_pocet</td></tr>";
//     }
//     $html.= "</table>";
//     break;
//
//   case 'ana_prispevky_y': # ----------------------------------------------------- ANA: příspěvky
//     $html= "<table class='stat'><tr><th>rok</th><th>příspěvků</th></tr>";
//     $rh= pdo_qry("
//       SELECT COUNT(*) AS _pocet,YEAR(datum) AS _rocnik
//       FROM gg_mbox
//       GROUP BY _rocnik
//       ORDER BY _rocnik DESC
//     ");
//     while ( $rh && (list($_pocet,$_rocnik)= pdo_fetch_row($rh)) ) {
//       $html.= "<tr><td>$_rocnik</td><td>$_pocet</td></tr>";
//     }
//     $html.= "</table>";
//     break;

  # ------------------------------------------------------------------------------------ ZÍSKÁNÍ DAT

  case 'upd_copy': # ------------------------------------------------------------ UPD: kopie gg_iOSOBA
//     // zjednodušená kopie z Answer
//     query("TRUNCATE TABLE gg_iosoba");
//     // extrakce osoby, osobního mailu a gmailu
//     query("INSERT INTO gg_iosoba (id_osoba,jmeno,prijmeni,email,iniciace)
//            SELECT id_osoba,jmeno,prijmeni,CONCAT(IF(kontakt,email,''),',',gmail),iniciace
//            FROM osoba WHERE iniciace!=0 AND deleted=''");
    // spojovací rekordy mezi maily a osoby
    query("TRUNCATE TABLE gg_osoba");
    query("INSERT INTO gg_osoba (email,zprav,prvni,posledni)
           SELECT LCASE(email),COUNT(*),MIN(datum),MAX(datum) FROM gg_mbox
           WHERE email!='chlapi-iniciace+noreply@googlegroups.com' GROUP BY email");
    // vytvoření tabulky mailu, gmailu, případně rodinného mailu --> id_osoba
    $id= array();
    $rh= pdo_qry("
      SELECT id_osoba,kontakt,IFNULL(LCASE(emaily),''),LCASE(email),LCASE(gmail)
      FROM osoba AS o
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      WHERE iniciace!=0 AND o.deleted='' AND IFNULL(r.deleted='',1)
    ");
    while ( $rh && (list($ido,$kontakt,$emaily,$email,$gmail)= pdo_fetch_row($rh)) ) {
      if ( $gmail )
        foreach(explode(',',$gmail) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
      if ( $email )
        foreach(explode(',',$email) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
      if ( !$kontakt && $emaily )
        foreach(explode(',',$emaily) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
    }
//                                                 debug($id);
    $rh= pdo_qry("SELECT email FROM gg_osoba");
    while ( $rh && (list($email)= pdo_fetch_row($rh)) ) {
      if ( isset($id[$email]) ) {
        query("UPDATE gg_osoba SET id_osoba={$id[$email]} WHERE email='{$email}'");
      }
    }
//     query("UPDATE gg_osoba AS e JOIN gg_iosoba AS i ON e.email=i.email SET e.id_osoba=i.id_osoba");
//     query("UPDATE gg_osoba AS e JOIN gg_iosoba AS i ON i.email RLIKE e.email SET e.id_osoba=i.id_osoba
//            WHERE e.id_osoba=0");
    break;

  case 'imap_db': # ------------------------------------------------------------- IMAP: uložit do db
    // vyprázdnit tabulku
    query("TRUNCATE gg_mbox");
    $sav= true;

  case 'imap': # ---------------------------------------------------------------- IMAP: test
    if ( $par->serv=='proglas' ) {
      $authhost= '{imap.proglas.cz:143}'.$par->mbox;
      $user="smidek@proglas.cz";
      $pass="************";
    }
    else { // gmail
      $authhost= '{imap.gmail.com:993/imap/ssl}'.$par->mbox;
      $user="***********";
      $pass="**********";
    }
    $mails= array();
    $mbox= @imap_open($authhost,$user,$pass);
    if ( !$mbox) { $msg.= print_r(imap_errors()); break; }
    $obj= imap_check($mbox);
//                                                 debug($obj);
    // zpracování vlákna
    $tree= imap_thread($mbox,SE_UID);
//                                                 debug($tree,count($tree));
    $num= $child= $parent= $next= $prev= $is= array();
    foreach ($tree as $key => $uid) {
      list($k,$type) = explode('.',$key);
      switch($type){
      case 'num':
        $is[$uid]= $k; $num[$k]= $uid; $mails[$uid]= (object)array();
        break;
      }
    }
    foreach ($tree as $key => $i) {
      list($k,$type) = explode('.',$key);
      switch($type){
      case 'next':   if ( $i ) { $child[$k]= $i; $parent[$i]= $k; } break;
      case 'branch': if ( $i ) { $next[$k]= $i; $prev[$i]= $k; } break;
      }
    }
    // najdeme kořen
    $first= -1;
    foreach ($num as $i=>$uid) {
      if ( !$parent[$i] && !$prev[$i] ) {
        $first= $i;
        break;
      }
    }
//                                                 display("first=$first");
    // poskládáme strukturu
    $root= $first;
    while ( $root>=0 ) {
      $mails[$num[$root]]->root= $num[$root];
      $root= isset($next[$root]) ? $next[$root] : -1;
    }
    foreach ($num as $root => $uid) {
      $family= array();
      $i= $root;
      $otec= -1;
      while ( $i>=0 && !isset($mails[$num[$i]]->root) ) {
        $otec= (isset($parent[$i]) ? $parent[$i] : -1);
        $k= isset($prev[$i]) ? $prev[$i] : $otec;
//                                                 display("$i - $k");
        if ( $k>=0 ) {
          $mails[$num[$i]]->back= $num[$k];
          $family[]= $i;
        }
        $i= $k;
      }
//                                                 debug($family,"root=$root");
      if ( $otec>=0 ) {
        foreach ($family as $i) {
          $mails[$num[$i]]->xroot= $num[$otec];
        }
      }
    }
    // spočítání odkazů
    foreach ($mails as $uid=>$mail) {
      if ( isset($mail->xroot) ) {
        $mails[$mail->xroot]->zprav++;
      }
    }
//                                                 debug($num,'uid ');
//                                                 debug($child,'child');
//                                                 debug($parent,'parent');
//                                                 debug($next,'next sibling');
//                                                 debug($prev,'prev sibling');
//                                                 debug($mails,'mails');


    // výběr
    //$cond= 'FROM "martin@smidek.eu" SINCE "10-Apr-2016"';
    $cond= 'ALL';
    //$cond= 'SINCE "15-Apr-2016"';
    $idms= imap_search($mbox,$cond,SE_UID);
//                                                 debug($idms);
//

    foreach ($idms as $idm) {
      $im= imap_msgno($mbox,$idm);
      $overview= imap_fetch_overview($mbox,$idm,FT_UID);
      // získání data
      $d= $overview[0]->date;
      $datum= strlen($d)==30 ? substr($d,5) : $d;
//                                                 display($datum);
      $utime= strtotime($datum);
      $datum= date("d.m.Y H:i:s",$utime);
      $ymdhis= date("Y-m-d H:i:s",$utime);
      // očištění from
      preg_match('/.*\<(.*)>/',$overview[0]->from,$m);
      $from= $m[1] ?: $overview[0]->from;
      // očištění subject
      $subj= mb_decode_mimeheader($overview[0]->subject);
      if ( strpos($subj,'=')!==false ) {
        $subj= "=?iso-8859-2?Q?$subj";
        $subj= mb_decode_mimeheader($subj);
      }
      $subj= str_replace("_"," ",$subj);
      $subj= preg_replace("/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i", '', $subj);
      $subj= preg_replace("/\[chlapi-iniciace]|\[chlapi-vsichni]|\[chlapi-informace]/i", '', $subj);
      $subj= trim($subj,"- \t\n\r\0\x0B");


//                                                 display($is[$idm]."/$idm: $datum from $from $subj");
      // zapiš do mails
      if ( isset($mails[$idm]->root) ) {
        $mails[$idm]->subj= $subj;
      }
      $mails[$idm]->date= $ymdhis;
      $mails[$idm]->from= $from;
      if ( !$sav ) {
//                                                debug($overview);
      }
    }
    if ( !$sav ) {
//                                                debug($mails,'mails');
    }
    if ( $sav ) {
      foreach ($mails as $uid=>$mail) {
        if ( $uid && $mail!='chlapi-iniciace+noreply@googlegroups.com' ) {
          // uložení do db
          $root=  isset($mail->root) ? $mail->root : $mail->xroot;
          $zprav= isset($mail->zprav) ? $mail->zprav : 0;
          $back=  isset($mail->back) ? $mail->back : 0;
          $subj=  isset($mail->subj) ? $mail->subj : '';
          query("INSERT INTO gg_mbox (uid,root,back,reakci,datum,email,nazev)
            VALUES ($uid,$root,$back,$zprav,'$mail->date','$mail->from','$subj')");
        }
      }
    }
    // konec
    imap_close($mbox);
    break;

  }
end:
  return $msg ? "ERROR: $msg<hr>$html" : $html;
}
