<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
# ================================================================================================== ÚČASTNÍCI
# ---------------------------------------------------------------------------------- akce_browse_ask
# obsluha browse s optimize:ask
function akce_browse_ask($x) {
//   return _akce_browse_ask($x);
  return test_one($x);
}
# ---------------------------------------------------------------------------------- akce_browse_ask
# obsluha browse s optimize:ask
function test_one($x=null) { trace();
  // dekódování seznamu položek na pole ...x,y=z... na [...x=>x,y=>z...]
  function flds($fstr) {
    $fp= array();
    foreach(explode(',',$fstr) as $fzp) {
      list($fz,$f)= explode('=',$fzp);
      $fp[$fz]= $f ?: $fz ;
    }
    return $fp;
  }
  global $test_clmn,$test_asc;
  if ( $x ) global $y;
  if ( !$x ) $x= (object)array('cmd'=>'browse_load');
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # ------------------------------------- browse_load
  default:
    # české řazení
//     setlocale(LC_ALL, "cs_CZ.UTF-8");
//     setlocale(LC_ALL, "cs_CZ.UTF-8", "Czech");
    setlocale(LC_ALL, "cs_CZ");
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
      WHERE p.id_akce=@akce AND funkce!=99 $AND ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $pobyt[$p->id_pobyt]= $p;
    }
    # seznam účastníků akce
    $qu= mysql_qry("
      SELECT s.*,o.narozeni
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      WHERE p.id_akce=@akce AND funkce!=99 $AND
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
      WHERE p.id_akce=@akce AND funkce!=99 $AND
    ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $osoby.= ",{$p->id_osoba}";
      $rodiny.= ",{$p->id_rodina}";
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_tvori= $p->id_tvori;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_rodina= $p->id_rodina;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->role= $p->role;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->narozeni= $p->narozeni;
    }
    # atributy osob
    $qo= mysql_qry("SELECT * FROM osoba AS o WHERE id_osoba IN (0$osoby)");
    while ( $qo && ($o= mysql_fetch_object($qo)) ) {
      $osoba[$o->id_osoba]= $o;
    }
    # atributy rodin
    $qr= mysql_qry("SELECT * FROM rodina AS r WHERE id_rodina IN (0$rodiny)");
    while ( $qr && ($r= mysql_fetch_object($qr)) ) {
      $rodina[$r->id_rodina]= $r;
    }
    # seznam rodin osob
    $qor= mysql_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina) SEPARATOR ','),'') AS _rody
      FROM osoba
      JOIN tvori USING(id_osoba)
      WHERE id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
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
          . ",platba,datplatby,cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,narozeni
    $fos=   flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
          . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk"
          . ",aktivita,note,_kmen");
    $fspo=  flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm");

    # 1. průchod - kompletace údajů mezi pobyty
    $skup= array();
    foreach ($pobyt as $idp=>$p) {
      # seřazení členů podle přítomnosti, role, věku
      uasort($p->cleni,function($a,$b) {
        $wa= $a->id_spolu==0 ? 4 : ( $a->role=='a' ? 1 : ( $a->role=='b' ? 2 : 3));
        $wb= $b->id_spolu==0 ? 4 : ( $b->role=='a' ? 1 : ( $b->role=='b' ? 2 : 3));
        return $wa == $wb ? ($a->narozeni==$b->narozeni ? 0 : ($a->narozeni > $b->narozeni ? 1 : -1))
                          : ($wa==$wb ? 0 : ($wa > $wb ? 1 : -1));

//         return $wa == $wb ? ($a->narozeni > $b->narozeni ? 1 : -1)
//                           : ($wa > $wb ? 1 : -1);

//         return $a->id_spolu==0 && $b->id_spolu!=0 ? 1 : (
//                $a->i0_rodina   ? ( $a->role == $b->role ? $a->narozeni > $b->narozeni : $a->role > $b->role )
//                                : $a->narozeni > $b->narozeni
//         );
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
          $_jmena.= "{$o->jmeno} ";
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
    $y->values= $zz;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($zz);
    $y->count= count($zz);
    $y->ok= 1;
    # případné řazení
    #    funkční řešení pro Windows
    #         setlocale(LC_ALL, "cs_CZ", "Czech");
    #         $ax= utf2win($a->$test_clmn,1); $bx= utf2win($b->$test_clmn,1);
    #         $c= $test_asc * strcoll($ax,$bx);
    if ( $x->order ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      setlocale(LC_ALL, "cs_CZ", "Czech");
      usort($y->values,function($a,$b) {
        global $test_clmn,$test_asc;
        $ax= utf2win($a->$test_clmn,1); $bx= utf2win($b->$test_clmn,1);
        $c= $test_asc * strcoll($ax,$bx);
        return $c;
      });
    }
    array_unshift($y->values,null);
  }
//                                                 debug($pobyt,'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba,'osoba');
//                                                 debug($y);
  return $y;
}
# ---------------------------------------------------------------------------------- akce_browse_ask
# obsluha browse s optimize:ask
function _akce_browse_ask($x) { trace($x->cmd);
  global $test_clmn,$test_asc;
  global $y;
  $y= (object)array();
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  $flds0= "ido,jmeno,vek";
  $flds1= "idt,idr,role,rodiny";
  $flds2= "prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
        . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk"
        . ",aktivita,note";
  $flds3= "dite_kat,poznamka,pecovane,pfunkce";
  $flds4= "luzka,pristylky,kocarek,pocetdnu,strava_cel,strava_pol"
        . ",platba1,platba2,platba3,platba4,platba,datplatby"
        . ",cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d,zpusobplat_d"
        . ",datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text";
//     show strava_oddo{ data:a.strava_oddo}
  $fosoba1= explode(',',$flds0);
  $fosoba2= explode(',',"narozeni,umrti,$flds2,kmen");
  $fspolu= "ids,barva,s_role,$flds3,pece_jm";
  $nespolu= str_repeat('~',substr_count($fspolu,','));
  $fspolu= explode(',',$fspolu);
  $ftvori= $flds1;
  $netvori= str_repeat('~',substr_count($ftvori,','));
  $ftvori= explode(',',$ftvori);
  $foo= str_replace(',',',oo.',$flds2);
  $fos= str_replace(',',',os.',$flds3);
  $faa= "'~',_a.".str_replace(',',",'~',_a.","$flds2,$flds3");
  $fbb= "'~',_b.".str_replace(',',",'~',_b.",$flds2);
  switch($x->cmd) {
  case 'browse_load':  # ------------------------------------- browse_load
    // zvětšení prostoru pro GROUP_CONCAT
    mysql_qry("SET group_concat_max_len = 65000;");
    // SQL definující @akce, @soubeh, @app
    if ( $x->sql ) mysql_qry($x->sql);
    // SQL
    $pobyt= $skup= array();
    $qry= "
      SELECT p.id_pobyt AS key_pobyt,p.id_akce as key_akce,
        p.i0_rodina AS key_rodina,DATEDIFF(a.datum_do,a.datum_od)+1 AS dnu,
        a.datum_od AS datum_od,
        IF(p.i0_rodina,r.nazev,_a.prijmeni) as _nazev,
        p.platba1+p.platba2+p.platba3+p.platba4+p.poplatek_d as c_suma,p.platba as platba,
        r.fotka as fotka,p.funkce as xfunkce,p.skupina as skupina,
        CASE
          WHEN @soubeh=1 AND a.ma_cenik THEN
             IF(p.platba1+p.platba2+p.platba3+p.platba4+p.poplatek_d=0,2,
               IF(p.platba1+p.platba2+p.platba3+p.platba4+p.poplatek_d>platba+platba_d,1,0))
          WHEN a.ma_cenik THEN
             IF(p.platba1+p.platba2+p.platba3+p.platba4=0,2,
               IF(p.platba1+p.platba2+p.platba3+p.platba4>platba,1,0))
          WHEN a.ma_cenu THEN
             IF(IF(pouze>0,1,2)*a.cena>platba,1,0)
          ELSE 0 END as dluh,
        r.spz AS r_spz,r.svatba AS r_svatba,
        IF(r.datsvatba,DATE_FORMAT(r.datsvatba,'%e.%c.%Y'),'') AS r_datsvatba,
        r.ulice AS r_ulice,r.psc AS r_psc,r.obec AS r_obec,r.stat AS r_stat,
        r.telefony AS r_telefony,r.emaily AS r_emaily,r.note AS r_note,
        p.poznamka AS p_poznamka,pokoj,budova,funkce,prednasi,
        $flds4,
        GROUP_CONCAT(DISTINCT _b.id_osoba,'~',_b.id_tvori,'~',_b.jmeno,'~',_b.prijmeni,
          '~',IF(_b.narozeni,DATE_FORMAT(_b.narozeni,'%e.%c.%Y'),''),
          '~',IF(_b.umrti,_b.umrti,''),
          '~',_b.id_rodina,'~',_b.role,'~',_b.rodne,'~',_b.sex
          ORDER BY role SEPARATOR '|') AS _rod,

        GROUP_CONCAT(DISTINCT _a._rody,'~',_a.id_osoba,'~',_a.id_spolu,'~',_a.jmeno,'~',_a.prijmeni,
          '~',IF(_a.narozeni,DATE_FORMAT(_a.narozeni,'%e.%c.%Y'),''),'~',IF(_a.umrti,_a.umrti,''),
          $faa SEPARATOR '|') AS _akce

        FROM pobyt AS p
        JOIN akce AS a ON a.id_duakce=p.id_akce
        LEFT JOIN rodina AS r ON id_rodina=i0_rodina

        LEFT JOIN (SELECT id_osoba,jmeno,prijmeni,id_tvori,id_rodina,role,rodne,sex,narozeni,umrti
          FROM osoba AS oo
          JOIN tvori AS ot USING(id_osoba)
        ) AS _b ON _b.id_rodina=r.id_rodina

        JOIN (SELECT oo.id_osoba,jmeno,id_spolu,id_pobyt,narozeni,umrti,$foo,$fos,
            IFNULL(GROUP_CONCAT(CONCAT(role,':',nazev,':',id_rodina) SEPARATOR ','),'') AS _rody
          FROM osoba AS oo
          JOIN spolu AS os ON os.id_osoba=oo.id_osoba AND os.id_pobyt=id_pobyt
          LEFT JOIN tvori AS ot ON ot.id_osoba=oo.id_osoba
          LEFT JOIN rodina AS otr USING(id_rodina)
          WHERE os.id_pobyt=id_pobyt
          GROUP BY id_spolu
        ) AS _a ON _a.id_pobyt=p.id_pobyt

        WHERE funkce!=99 AND p.id_akce=@akce -- AND p.id_pobyt IN (20825) -- AND p.id_pobyt IN (20487) --  AND p.id_pobyt IN (20568,20793) -- AND _b.id_rodina=7 -- AND p.id_pobyt IN (20793,20568) -- AND _a.id_osoba=369 -- /*AND _b.id_osoba= 2015 --*/
        GROUP BY p.id_pobyt
        /*ORDER BY IF(funkce<=2,1,funkce),_nazev*/
        ORDER BY p.id_pobyt DESC
        ";
/*
        LIMIT 1";
        LIMIT 3";
        LIMIT 80,1"; // Glogar
*/
    $qp= mysql_qry($qry);
    if ( !$qp ) fce_warning(mysql_error());
    $osoba= array();                            // atributy osob na akci
    $spolu= array();                            // $spolu[id_pobyt,id_osoba]= id_spolu,$flds3
    $tvori= array();                            // $tvori[id_pobyt,id_osoba]= id_tvori,id_rodina,role,rodiny
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
//                                                         debug($p);
      $idp= $p->key_pobyt;
      // vytvoření seznamu členů
      $cleni= $nazev= array();
      // členové nastavené rodiny
      if ( $p->_rod ) {
        foreach (explode('|',$p->_rod) as $rod) {
          $r= explode('~',$rod);
          $ido= $r[0];
          // kmenová rodina tzn. kde je 'a' nebo 'b'
          $c= isset($osoba[$ido]) ? $osoba[$ido] : (object)array('kmen'=>'','idp'=>$idp);
          list($ido,$c->idt,$c->jmeno,$c->prijmeni,$c->narozeni,$c->umrti,
            $c->idr,$c->role,$c->rodne,$c->sex)= $r;
          $c->ido= $ido;
          $c->vek= roku_k($c->narozeni,$p->datum_od);
          $rodiny= "{$p->_nazev}:{$c->idr}";
          $cleni[]= $ido;
          $tvori[$idp][$ido]= (object)array(
            'idt'=>$c->idt,'idr'=>$c->idr,'role'=>$c->role,'rodiny'=>$rodiny);
          $osoba[$ido]= $c;
        }
      }
      // osoby na akci v rámci jednoho pobytu
      foreach (explode('|',$p->_akce) as $akce) {
        $a= explode('~',$akce);
        $ido= $a[1];
        $ids= $a[2];
        $prijmeni= $a[4];
        $s= (object)array('ids'=>$ids,'pece_jm'=>'','s_role'=>0);
        if ( !isset($tvori[$idp][$ido]) ) $tvori[$idp][$ido]= (object)array();
        if ( !in_array($ido,$cleni) ) {
          $cleni[]= $ido;
        }
        if ( isset($osoba[$ido]) ) {
          $c= $osoba[$ido];
          $s->barva= 1;
        }
        else {
          $c= (object)array();
          $s->barva= 2;
        }
        // kmenová rodina
        $rody= explode(',',$a[0]);
        $r= "-:0"; $kmen= '';
        foreach($rody as $rod) {
          list($role,$naz,$idr)= explode(':',$rod);
          $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
          $r.= ",$naz:$idr";
        }
        $c->kmen= $kmen;
        $tvori[$idp][$ido]->rodiny= $r;
        // přepsání
        $i= 1;
        foreach(explode(',',"ido,ids,jmeno,prijmeni,narozeni,umrti,$flds2") as $f) {
          $c->$f= $a[$i]; $i++;
        }
        foreach(explode(',',"$flds3") as $f) {
          $s->$f= $a[$i]; $i++;
        }
        if ( $ids && !in_array(trim($prijmeni),$nazev) ) {
          $nazev[]= trim($prijmeni);
        }
        // výpočet věku
        $c->vek= roku_k($c->narozeni,$p->datum_od);
        $osoba[$ido]= $c;
        $spolu[$idp][$ido]= $s;
        // oprava rodin
        $tvori[$idp][$ido]->rodiny= "-:0".(isset($tvori[$idp][$ido]->rodiny) ?
          ",{$tvori[$idp][$ido]->rodiny}" : '');
      }
      // skupinka?
      if ( $p->skupina ) $skup[$p->skupina][]= $idp;
      // zápis
      $idr= $p->key_rodina;
      $nazev= $idr ? $p->_nazev : implode(' ',$nazev);

      $pobyt[$idp]= (object)array(
        'dnu'=>$p->dnu,'datum_od'=>$p->datum_od,
        'key_pobyt'=>$idp,'key_akce'=>$p->key_akce,'key_spolu'=>0,'key_osoba'=>0,'key_rodina'=>$idr,
        '_nazev'=>$nazev, '_jmena'=>'',
        'c_suma'=>$p->c_suma,'platba'=>$p->platba,'fotka'=>$p->fotka,
        'xfunkce'=>$p->xfunkce,'skupina'=>$p->skupina,'dluh'=>$p->dluh,
        // rodina
        'r_spz'=>$p->r_spz,'r_svatba'=>$p->r_svatba,'r_datsvatba'=>$p->r_datsvatba,
        'r_ulice'=>$p->r_ulice,'r_psc'=>$p->r_psc,'r_obec'=>$p->r_obec,'r_stat'=>$p->r_stat,
        'r_telefony'=>$p->r_telefony,'r_emaily'=>$p->r_emaily,'r_note'=>$p->r_note,
        // r_cleni
        'r_cleni'=>$cleni,
        // rodina pobyt
        'p_poznamka'=>$p->p_poznamka,'pokoj'=>$p->pokoj,'budova'=>$p->budova,'funkce'=>$p->funkce,
        'prednasi'=>$p->prednasi,
        // platba pobyt
        'luzka'=>$p->luzka,'pristylky'=>$p->pristylky,'kocarek'=>$p->kocarek,
        'pocetdnu'=>$p->pocetdnu,'strava_cel'=>$p->strava_cel,'strava_pol'=>$p->strava_pol,
        // ... přejmenování platba?
        'c_nocleh'=>$p->platba1,'c_strava'=>$p->platba2,'c_program'=>$p->platba3,'c_sleva'=>$p->platba4,
        'platba'=>$p->platba,'datplatby'=>$p->datplatby,
        'cstrava_cel'=>$p->cstrava_cel,'cstrava_pol'=>$p->cstrava_pol,'svp'=>$p->svp,
        'zpusobplat'=>$p->zpusobplat,'naklad_d'=>$p->naklad_d,'poplatek_d'=>$p->poplatek_d,
        'platba_d'=>$p->platba_d,'zpusobplat_d'=>$p->zpusobplat_d,'datplatby_d'=>$p->datplatby_d,
        'ubytovani'=>$p->ubytovani,'cd'=>$p->cd,'avizo'=>$p->avizo,'sleva'=>$p->sleva,
        'vzorec'=>$p->vzorec,'duvod_typ'=>$p->duvod_typ,'duvod_text'=>$p->duvod_text,
        // vypočítané údaje
        'skup'=>0,
        'ido1'=>0,
        'ido2'=>0
      );
    }
    // ------------------------------------------------------------ vše je načteno, probíhá doplnění
    // doplnění informací
    foreach($pobyt as $idp=>$u) {
      foreach($pobyt[$idp]->r_cleni as $ido) {
        $o= $osoba[$ido];
        $s= $spolu[$o->idp][$ido];
        $o_jmeno= $o->prijmeni.' '.$o->jmeno;
        if ( $idop= $s->pecovane ) {
          $op= $osoba[$idop];
          $sp= $spolu[$op->idp][$idop];
          $op_jmeno= $op->prijmeni.' '.$op->jmeno;
          // -- dítě s os. pečovatelem (s_role=3,pfunkce=92)
          $s->pece_jm= $op_jmeno;
          $s->s_role= 5;
          $s->barva= 5;
          if ( $s->pfunkce!=95 ) fce_warning("$op_jmeno: konflikt funkcí {$s->s_role}/$s->pfunkce");
          // -- osobní pečovatel (s_role=5,pfunkce=95)
          $sp->pece_jm= $o_jmeno;
          $sp->s_role= 3;
          $sp->barva= 3;
          if ( $sp->pfunkce!=92 ) fce_warning("$o_jmeno: konflikt funkcí {$sp->s_role}/$sp->pfunkce");
        }
      }
    }
    foreach($pobyt as $idp=>$u) {
      foreach($pobyt[$idp]->r_cleni as $ido) {
        $o= $osoba[$ido];
        if ( isset($spolu[$o->idp][$ido]) ) {
        $s= $spolu[$o->idp][$ido]; //= (object)array();
        if ( !$s->s_role ) {
          // stanovení zbylých s_role a kontrola proti spolu.pfunkce
          $o_jmeno= $o->prijmeni.' '.$o->jmeno;
          if ( $s->pfunkce==4 ) {       // pom.peč.
            $s->s_role= 4;
          }
          elseif ( $s->pfunkce==8 ) {   // skupina G
            $s->s_role= 6;
          }
          elseif ( $o->vek<18 ) {       // dítě
            $s->s_role= 2;
            if ( $s->pfunkce!=0 ) fce_warning("$o_jmeno: konflikt funkcí {$s->s_role}/$s->pfunkce");
          }
          else {                        // zbytek
            $s->s_role= 1;
            if ( $s->pfunkce!=0 ) fce_warning("$o_jmeno: konflikt funkcí {$s->s_role}/$s->pfunkce");
          }
          }
        }
      }
    }
    // transformace pobytu pro browse_fill
    foreach($pobyt as $idp=>$u) {
      $cleni= $del= '';
      // členi rodiny resp. pobytu
      foreach($u->r_cleni as $ido) {
        foreach($fosoba1 as $f) {
          $cleni.= "$del{$osoba[$ido]->$f}"; $del= '~';
        }
        if ( $t= $tvori[$idp][$ido] ) {
          foreach($ftvori as $f) {
            $cleni.= "$del{$t->$f}"; $del= '~';
          }
        }
        else {
          $cleni.= "$del$netvori";
        }
        foreach($fosoba2 as $f) {
          $cleni.= "$del{$osoba[$ido]->$f}"; $del= '~';
        }
        if ( $s= $spolu[$idp][$ido] ) {
          foreach($fspolu as $f) {
            $cleni.= "$del{$s->$f}"; $del= '~';
          }
          // seznam jmen
          $u->_jmena.= "{$osoba[$ido]->jmeno} ";
          // první 2 členi na pobytu
          if ( !$u->ido1 )
            $u->ido1= $ido;
          elseif ( !$u->ido2 )
            $u->ido2= $ido;
        }
        else {
          $cleni.= "$del$nespolu";
        }
      }
      $pobyt[$idp]->r_cleni= $cleni;
      // doplnění skupinek
      $s= $del= '';
      if ( ($sk= $u->skupina) && $skup[$sk]) {
        foreach($skup[$sk] as $ip) {
          $s.= "$del$ip~{$pobyt[$ip]->_nazev}";
          $del= '~';
        }
      }
      $pobyt[$idp]->skup= $s;
    }
//                                                   debug($tvori,"tvori");
//                                                   debug($spolu,"spolu");
//                                                   debug($osoba,"osoby");
//                                                   debug($pobyt,"pobyty");
    // předání ve formátu browse
    $y->values= $pobyt;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($pobyt);
    $y->count= count($pobyt);
    $y->ok= 1;
    // případné řazení
    if ( $x->order ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      usort($y->values,function($a,$b) {
        global $test_clmn,$test_asc;
        $c= $test_asc*($a->$test_clmn>$b->$test_clmn ? 1 : ($a->$test_clmn<$b->$test_clmn ? -1 : 0));
        return $c;
      });
    }
    array_unshift($y->values,null);
    break;
  default:
    fce_warning("N.Y.I. test_browse_ask/{$x->cmd}");
    $y->ok= 0;
    break;
  }
  return $y;
}
?>
