<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
/** ===========================================================================================> DB2 **/
# ------------------------------------------------------------------------------------- db2_rod_show
# vrátí objekt {n:int,next:bool,back:bool,css:string,rod:položky z rodina nebo null}
function db2_rod_show($nazev,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','rod'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $nazev= trim($nazev);
  $rod= array(null);
  // načtení rodin
  $qr= mysql_qry("SELECT id_rodina AS key_rodina,ulice AS r_ulice,psc AS r_psc,obec AS r_obec,
      telefony AS r_telefony,emaily AS r_emaily,spz AS r_spz,datsvatba,access
    FROM rodina WHERE deleted='' AND nazev='$nazev'");
  while ( $qr && ($r= mysql_fetch_object($qr)) ) {
    $r->r_datsvatba= sql_date1($r->datsvatba);
    $rod[]= $r;
  }
//                                                         debug($rod,count($rod));
  // diskuse
  $ret->last= count($rod)-1;
  if ( isset($rod[$n]) ) {
    $ret->n= $n;
    $ret->rod= $rod[$n];
    $ret->back= $n>1 ?1:0;
    $ret->next= $n<count($rod)-1 ?1:0;
    $ret->css= $css[$ret->rod->access];
    // seznam členů rodiny
    $cleni= $del= '';
    $idr= $ret->rod->key_rodina;
    $qc= mysql_qry("
      SELECT id_osoba,id_tvori,access,prijmeni,jmeno,role,narozeni
      FROM osoba AS o
      JOIN tvori AS t USING(id_osoba)
      WHERE t.id_rodina=$idr
      ORDER BY role,narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
      $vek= $c->narozeni!='0000-00-00' ? roku_k($c->narozeni) : '?'; // výpočet věku
      $cleni.= "$del$ido~{$c->access}~{$c->jmeno}~$vek~{$o->id_tvori}~$idr~{$c->role}";
      $cleni.= str_repeat('~',32).'1'.str_repeat('~',8);;
      $del= '~';
    }
    $ret->cleni= $cleni;
  }
//                                                         debug($ret,'db2_rod_show');
  return $ret;
}
# ------------------------------------------------------------------------------------- db2_oso_show
# vrátí objekt {n:int,next:bool,back:bool,css:string,oso:položky z osoba nebo null}
function db2_oso_show($prijmeni,$jmeno,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','oso'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $prijmeni= trim($prijmeni);
  $jmeno= trim($jmeno);
  $oso= array(null);
  // načtení rodin
  $qr= mysql_qry("SELECT id_osoba AS key_osoba,access,rodne,sex,narozeni,umrti,
      adresa,ulice,psc,obec,kontakt,telefon,email
    FROM osoba WHERE deleted='' AND prijmeni='$prijmeni' AND jmeno='$jmeno' ");
  while ( $qr && ($r= mysql_fetch_object($qr)) ) {
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
                                                        debug($ret,'db2_oso_show');
  return $ret;
}
/** ========================================================================================> UCAST2 **/
# -------------------------------------------------------------------------------- ucast2_browse_ask
# obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou je oddělovače řádků použit znak '≈' (oddělovač sloupců zůstává '~')
function ucast2_browse_ask($x,$tisk=false) {
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
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> browse_load
  default:
    # vnořené SQL definující @akce, @soubeh, @app
    if ( $x->sql ) mysql_qry($x->sql);
    $pobyt= array();              // $pobyt[id_pobyt]             vše
    $skup= array();               // $skup[skupina]               seznam id_pobyt
    $osoba= array();              // $osoba[id_osoba]             atributy osob na akci
    $cleni= "";
    $osoby= "";
    $rodina= array();             // $rodina[id_rodina]           atributy rodin na akci
    $rodina_pobyt= array();       // $rodina[i0_rodina]=id_pobyt  pobyt rodiny (je-li rodinný)
    $rodiny= "";
    $spolu= array();              // $spolu[id_osoba]             id_pobyt
    $tvori= array();              // $tvori[id_pobyt,id_osoba]    id_tvori,id_rodina,role,rodiny
    # ladění
    $AND= "";
//     $AND= "AND p.id_pobyt IN (44285,44279,44280,44281) -- prázdná rodina a pobyt";
//     $AND= "AND p.id_pobyt IN (44279) -- test";
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
      SELECT @akce,@soubeh AS soubeh,@app,
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
      if ( $p->i0_rodina ) {
        $rodina_pobyt[$p->i0_rodina]= $p->id_pobyt;
        $pobyt[$p->id_pobyt]->access= $p;
        $rodiny.= ($rodiny?',':'').$p->i0_rodina;
      }
    }
//                                                         debug($rodina_pobyt,"rodina_pobyt");
    # seznam účastníků akce - podle podmínky
    $qu= mysql_qry("
      SELECT s.*,o.narozeni,MIN(CONCAT(IF(role='','?',role),id_rodina)) AS _role,o_umi
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
      // doplnění osobního umí - malým
      if ( $u->o_umi ) {
        $pobyt[$u->id_pobyt]->x_umi.= strtolower($umi($u->o_umi));
      }
    }
    $osoby.= $cleni;
    # seznam rodinných příslušníků
    $qp= mysql_qry("
      SELECT id_pobyt,id_rodina,id_tvori,id_osoba,role,o_umi,o.narozeni
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
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->o_umi= $p->o_umi;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->narozeni= $p->narozeni;
    }
    # atributy rodin
    $qr= mysql_qry("SELECT * FROM rodina AS r WHERE deleted='' AND id_rodina IN (0$rodiny)");
    while ( $qr && ($r= mysql_fetch_object($qr)) ) {
      $r->datsvatba= sql_date1($r->datsvatba);                  // svatba d.m.r
      if ( $r->r_umi && $rodina_pobyt[$r->id_rodina] ) {
        // umí-li něco rodina a je na pobytu - velkým
        $pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi=
          strtoupper($umi($r->r_umi)).' '.$pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi;
      }
      $rodina[$r->id_rodina]= $r;
    }
    # atributy osob
    $qo= mysql_qry("SELECT * FROM osoba AS o WHERE deleted='' AND id_osoba IN (0$osoby)");
    while ( $qo && ($o= mysql_fetch_object($qo)) ) {
      $osoba[$o->id_osoba]= $o;
    }
    # seznam rodin osob
    $qor= mysql_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina,
          IF(r.access=1,':ezer_ys',IF(r.access=2,':ezer_fa',IF(r.access=3,':ezer_db','')))
        ) SEPARATOR ','),'') AS _rody,
        SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
      FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
      WHERE o.deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array();
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
      foreach (explode(',',$or->_rody) as $rod) {
        list($role,$idr,$css)= explode(':',$rod);
        if ( !$rodina[$idr] ) {
          # doplnění (potřebných) rodinných údajů pro kmenové rodiny
//                                                         display("{$or->id_osoba} - $kmen");
          $qr= mysql_qry("
            SELECT * -- id_rodina,nazev,ulice,obec,psc,stat,telefony,emaily
            FROM rodina AS r WHERE id_rodina=$idr");
          while ( $qr && ($r= mysql_fetch_object($qr)) ) {
            $rodina[$idr]= $r;
          }
        }
      }
    }
//                                                         display("rodiny:$rodiny");
//                                                         debug($rodina,$rodiny);
//                                                         debug($osoba,'osoby po _rody');
    # seznamy položek
    $fpob1= flds("key_pobyt=id_pobyt,_empty=0,key_akce=id_akce,key_osoba,key_spolu,key_rodina=i0_rodina,"
           . "c_suma,platba,xfunkce=funkce,funkce,skupina,dluh");
    $fakce= flds("dnu,datum_od");
    $frod=  flds("fotka,r_access=access,r_spz=spz,r_svatba=svatba,r_datsvatba=datsvatba,r_rozvod=rozvod,r_ulice=ulice,r_psc=psc,"
          . "r_obec=obec,r_stat=stat,r_telefony=telefony,r_emaily=emaily,r_umi,r_note=note");
    $fpob2= flds("p_poznamka=poznamka,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_pol,c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4"
          . ",datplatby,cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text,x_umi");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,narozeni
    $fos=   flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
          . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
          . ",aktivita,note,_kmen");
    $fspo=  flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id,o_umi");

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
      $p_access= 0;
      $idr= $p->i0_rodina ?: 0;
      $p->access= 5;
      $z= (object)array();
      $_ido01= $_ido02= $_ido1= $_ido2= 0;
      # agregace informací z členů pobytu
      $nazev= array();
      $_jmena= "";
      $clenu= 0;
      $cleni= ""; $del= "";
      if ( count($p->cleni) ) {
        foreach ($p->cleni as $ido=>$s) {
          $o= $osoba[$ido];
          # první 2 členi v rodině
          if ( !$_ido01 )
            $_ido01= $ido;
          elseif ( !$_ido02 )
            $_ido02= $ido;
          if ( $s->id_spolu ) {
            # spočítání účastníků kvůli platbě
            $clenu++;
            # první 2 členi na pobytu
            if ( !$_ido1 )
              $_ido1= $ido;
            elseif ( !$_ido2 )
              $_ido2= $ido;
            # výpočet jmen pobytu
            $_jmena.= str_replace(' ','-',trim($o->jmeno))." ";
            if ( !$idr ) {
              # výpočet názvu pobyt
              $prijmeni= $o->prijmeni;
              if ( !in_array(trim($prijmeni),$nazev) ) $nazev[]= trim($prijmeni);
            }
            # barva
            if ( !$s->_barva )
              $s->_barva= $s->id_tvori ? 1 : 2;               // barva: 1=člen rodiny, 2=nečlen
            # barva nerodinného pobytu
            if ( 1 ) {
              $p_access|= $o->access;
            }
          }
          # ==> .. seznam členů pro browse_fill
          $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni,$akce->datum_od) : '?'; // výpočet věku
          $cleni.= "$del$ido~{$o->access}~{$o->jmeno}~$vek~{$s->id_tvori}~{$s->id_rodina}~{$s->role}";
          $del= $delim;
          # ==> .. rodiny a kmenová rodina
          $rody= explode(',',$o->_rody);
          $r= "-:0:nerodina"; $kmen= '';
          foreach($rody as $rod) {
            list($role,$ir,$access)= explode(':',$rod);
            $naz= $rodina[$ir]->nazev;
            $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
  //                                                 display("$o->jmeno/$role: $kmen ($naz,$ir)");
            $r.= ",$naz:$ir:$access";
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
      }
//                                                   debug($p->cleni,"členi");
//                                                   display($cleni);
      $_nazev= $idr ? $rodina[$idr]->nazev : ($nazev ? implode(' ',$nazev) : '-');
      # zjištění dluhu
      $platba1234= $p->platba1 + $p->platba2 + $p->platba3 + $p->platba4;
      $p->c_suma= $platba1234 + $p->poplatek_d;
      $p->dluh= $akce->soubeh==1 && $akce->ma_cenik
        ? ( $p->c_suma == 0 ? 2 : ( $p->c_suma > $p->platba+$p->platba_d ? 1 : 0 ) )
        : ( $akce->ma_cenik
          ? ( $platba1234 == 0 ? 2 : ( $platba1234 > $p->platba ? 1 : 0) )
          : ( $akce->ma_cenu ? ( $clenu * $akce->cena > $p->platba ? 1 : 0) : 0 )
          );
//                                                         if ($idp==15826) { debug($akce);debug($p,"platba1234=$platba1234"); }
      # pobyt I
      foreach($fpob1 as $fz=>$fp) { $z->$fz= $p->$fp; }
      # akce
      foreach($fakce as $fz=>$fp) { $z->$fz= $akce->$fp; }
      $z->_nazev= $_nazev;
      $z->_jmena= $_jmena;
      # jestli jsou dokumenty
//       $z->_docs= drop_find("pobyt/","^(.*)_$idp\$") ? 'D' : '';  --- ucast2 musí měnit složku aplikace
//                                         display("drop_find(pobyt/,^(.*)_$idp\$)={$z->_docs}");
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= $rodina[$idr]->$fr; }
      # ... oprava obarvení
      $z->r_access= $p_access;
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->key_spolu= 0;
      $z->ido1= $_ido1 ?: $_ido01;
      $z->ido2= $_ido2; // ?: $_ido02;
      $z->datplatby= sql_date1($z->datplatby);                   // d.m.r
      $z->datplatby_d= sql_date1($z->datplatby_d);               // d.m.r
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
//                                                 debug($pobyt[21976],'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba[3506],'osoba');
//                                                 debug($y->values);
  return $y;
}
# ----------------------------------------------------------------------------- ucast2_pridej_rodinu
# ASK přidání rodinného pobytu do akce (pokud ještě nebyla rodina přidána)
function ucast2_pridej_rodinu($id_akce,$id_rodina) { trace();
  $ret= (object)array('idp'=>0,'msg'=>'');
  // kontrola nepřítomnosti
  $jsou= select1('COUNT(*)','pobyt',"id_akce=$id_akce AND i0_rodina=$id_rodina");
  if ( $jsou ) { // už jsou na akci
    $ret->msg= "... rodina již je přihlášena na akci";
  }
  else {
    // vložení nového pobytu
    $rod= $pouze==0 && $info->rod ? $info->rod : 0;
    // přidej k pobytu
    $ret->idp= ezer_qry("INSERT",'pobyt',0,array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$id_akce),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$id_rodina)
    ));
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
# spolupracuje s číselníky: ms_akce_s_role,ms_akce_dite_kat
#   podle stáří resp. role odhadne hodnotu SPOLU.s_role a SPOLU.dite_kat
function ucast2_pridej_osobu($ido,$access,$ida,$idp,$idr=0,$role=0) { trace();
  $ret= (object)array('spolu'=>0,'tvori'=>0,'msg'=>'');
  list($narozeni,$old_access)= select("narozeni,access","osoba","id_osoba=$ido");
  # případné vytvoření pobytu
  if ( !$idp ) {
    $idp= ezer_qry("INSERT",'pobyt',0,array(
    (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$ida),
    (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$idr)
  ));
  }
  # přidání k pobytu
  $je= select("COUNT(*)","pobyt JOIN spolu USING(id_pobyt)","id_akce=$ida AND id_osoba=$ido");
  if ( $je ) { $ret->msg= "osoba už na této akci je"; goto end; }
  // pokud na akci ještě není, zjisti pro děti (<18 let) s_role a dite_kat
  $datum_od= select("datum_od","akce","id_duakce=$ida");
  $vek= roku_k($narozeni,$datum_od);
  $kat= 0; $srole= 1;                                         // default= účastník, nedítě
  // odhad typu účasti podle stáří a role
  if     ( $role=='p' )                         { $kat= 0; $srole= 5; }   // osob.peč.
  elseif ( $vek>=18 || $narozeni=='0000-00-00') { $kat= 0; $srole= 1; }   // účastník
  elseif ( (!$role || $role=='d') && $vek>=17 ) { $kat= 1; $srole= 2; }   // dítě - A|G
  elseif ( (!$role || $role=='d') && $vek>=13 ) { $kat= 1; $srole= 2; }   // dítě - A
  elseif ( (!$role || $role=='d') && $vek>=3 )  { $kat= 3; $srole= 2; }   // dítě - C
  elseif ( (!$role || $role=='d') && $vek>=2 )  { $kat= 5; $srole= 2; }   // dítě - E
  elseif ( (!$role || $role=='d') && $vek>=0 )  { $kat= 6; $srole= 3; }   // dítě - F
  // přidej k pobytu
  $ret->spolu= ezer_qry("INSERT",'spolu',0,array(
    (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
    (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
    (object)array('fld'=>'s_role',   'op'=>'i','val'=>$srole),
    (object)array('fld'=>'dite_kat', 'op'=>'i','val'=>$kat)
  ));
  # přidání do rodiny
  if ( $idr && $role ) {
    $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
    if ( $je ) { $ret->msg= "osoba už v této rodině je"; goto end; }
    // pokud v rodině ještě není, přidej
    $ret->tvori= ezer_qry("INSERT",'tvori',0,array(
      (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
      (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
      (object)array('fld'=>'role',     'op'=>'i','val'=>"'$role'")
    ));
  }
  # úprava access, je-li třeba
  if ( $access && $access!=$old_access) {
    ezer_qry("UPDATE",'osoba',$ido,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$old_access)
    ));
  }
end:
//                                                 debug($ret,'ucast2_pridej_osobu / $vek $kat $srole');
  return $ret;
}
/** =========================================================================================> EVID2 **/
# --------------------------------------------------------------------------------------- evid2_cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid2_cleni($id_osoba,$id_rodina,$filtr) { trace();
  global $USER;
  $access= $USER->access;
  $msg= '';
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
//   $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $css= array('','ezer_ys','ezer_fa','ezer_db');
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access AS o_access,
        rt.id_tvori,rt.role,o.deleted,r.id_rodina,nazev,r.access AS r_access
      FROM osoba AS o
        JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
        JOIN rodina AS r ON r.id_rodina=ot.id_rodina -- AND r.access & $access
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
      WHERE o.id_osoba=$id_osoba AND $filtr -- AND rto.access & $access
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
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
    $qc= mysql_qry("
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
      WHERE r.id_rodina=$id_rodina AND $filtr AND rto.access & $access
      GROUP BY id_osoba
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
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
# ----------------------------------------------------------------------------- evid2_browse_act_ask
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
    $qp= mysql_qry("
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
/** ==========================================================================================> STA2 **/
# ================================================================================> . sta2_struktura
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
function sta2_struktura($org,$par,$title,$export=false) {
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= sta2_akcnost_vps($org,$par,$title,true);
//                                                    debug($tab,"evid_sestava_v(,$title,$export)");
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
         WHERE a.druh=1 AND p.funkce IN (0,1) AND spec=0 AND a.access & $org
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
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- sta2_akcnost_vps
# generování přehledu akčnosti VPS
function sta2_akcnost_vps($org,$par,$title,$export=false) {
  // dekódování parametrů
  $roky= '';
  for ($r=1990;$r<=date('Y');$r++) {
    $roky.= ','.substr($r,-2);
    $froky.= ','.substr($r,-2).':3';
  }
  $tits= explode(',',$tit= $par->tit.$froky);
  $flds= explode(',',$fld= $par->fld.$roky);
  $HAVING= $par->hav ? "HAVING {$par->hav}" : '';
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
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_jmeno':                            // kolektivní člen
        $clmn[$n][$f]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}";
        break;
      default:
        $clmn[$n][$f]= $x->$f;
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
# --------------------------------------------------------------------------------- sta2_table_graph
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function sta2_table_graph($par,$tits,$flds,$clmn,$export=false) {
  $result= (object)array('par'=>$par);
  if ( $par->grf ) {
    list($norm,$grf)= explode(':',$par->grf);
  }
  $skin= $_SESSION['skin'];
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
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
        if ( strpos(",$grf,",",$f,")!==false ) {
          $g= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;
          $g= "<img src='skins/$skin/pixel.png'
            style='height:4px;width:{$g}px;float:left;margin-top:5px'>";
        }
        $align= is_numeric($c[$f]) || preg_match("/\d+\.\d+\.\d+/",$c[$f]) ? "right" : "left";
        $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
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
# ==================================================================================> . sta2_sestava
# sestavy pro evidenci
function sta2_sestava($org,$title,$par,$export=false) {
//                                                 debug($par,"sta2_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  // dekódování parametrů
  $tits= $par->tit ? explode(',',$par->tit) : array();
  $flds= $par->fld ? explode(',',$par->fld) : array();
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {
  # Sestava pečounů na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'pecujici':     // -----------------------------------==> .. pecujici
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $tits= array("pečovatel:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10","1.školení:10",
                 "č.člen od:10","bydliště:25","narození:10","(ID osoby)");
    $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    $rx= mysql_qry("SELECT
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
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
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
  # Sestava sloužících na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'slouzici':     // -------------------------------------==> .. slouzici
    global $VPS;
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $vps1= $org==1 ? '3,17' : '3';
    if ( $par->podtyp=='pary' ) {
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","(ID)");
      $flds= array('jm','od','n','do','vps_i','clen','^id_rodina');
    }
    else { // osoby
      $tits= array("jméno:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","bydliště:25","narození:10","(ID)");
      $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    }
    $rx= mysql_qry("SELECT
        r.id_rodina,r.nazev,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_m,
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
      WHERE spec=0 AND r.id_rodina=i0_rodina AND a.access&$org
        -- AND r.nazev LIKE 'Šmí%'
        AND druh IN (1,$vps1)
      GROUP BY r.id_rodina
      -- HAVING -- bereme vše kvůli číslům certifikátů - vyřazuje se až při průchodu
        -- VPS_I<9999 AND
        -- DO>=$hranice
      ORDER BY r.nazev");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
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
        WHERE a.druh=1 AND p.prednasi=$pr AND YEAR(a.datum_od) BETWEEN $od AND $do AND a.access&$org
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
  case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
    # _pec,_sko,_proc
    list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1 AND access&$org");
    for ($rok=$od; $rok>=$do; $rok--) {
      $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok AND access&$org");
      $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok AND access&$org");
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
  case 'ucast-vek': // ---------------------------------------------==> .. ucast-vek
    $rok= date('Y')-$par->rok;
    $rx= mysql_qry("
      SELECT YEAR(a.datum_od)-YEAR(o.narozeni) AS _vek,MAX(p.funkce) AS _fce
      FROM osoba AS o
      JOIN spolu AS s USING(id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce  AS a ON id_akce=id_duakce
      WHERE o.deleted='' AND YEAR(datum_od)=$rok AND a.access&$org
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
      WHERE o.deleted='' AND YEAR(narozeni)<$rok18 AND a.access&$org
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
    return sta2_table($tits,$flds,$clmn,$export);
}
# ---------------------------------------------------------------------------------- sta2_ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function sta2_ukaz_osobu($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_osoba/$ido'>$ido</a></b>";
}
# --------------------------------------------------------------------------------- sta2_ukaz_rodinu
# zobrazí odkaz na rodinu v evidenci
function sta2_ukaz_rodinu($idr,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_rodina/$idr'>$idr</a></b>";
}
# ---------------------------------------------------------------------------------- sta2_ukaz_pobyt
# zobrazí odkaz na řádek s pobytem
function sta2_ukaz_pobyt($idp,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.ucast.ucast_pobyt/$idp'>$idp</a></b>";
}
# --------------------------------------------------------------------------------- sta2_excel_subst
function sta2_sestava_adresy_fill($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# --------------------------------------------------------------------------------------- sta2_table
function sta2_table($tits,$flds,$clmn,$export=false) {  trace();
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
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".sta2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".sta2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".sta2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else {
//                                 debug($c,$f); return $ret;
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
# ---------------------------------------------------------------------------------------- sta2_excel
# generování tabulky do excelu
function sta2_excel($org,$title,$par,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  $subtitle= "ke dni ".date("j. n. Y");
  if ( !$tab ) {
    $tab= sta2_sestava($org,$title,$par,true);
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
# ---------------------------------------------------- sta2_excel_subst
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
# ------------------------------------------------------------------------------------- elim2_differ
# do _track potvrdí, že $id_orig,$id_copy jsou různé osoby nebo rodiny
function elim2_differ($id_orig,$id_copy,$table) { trace();
  global $USER;
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
# -------------------------------------------------------------------------------------- elim2_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_osoba($id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'','d','osoba',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','kopie',$id_orig)");    // x=smazání
end:
  return $ret;
}
# --------------------------------------------------------------------------------------- elim2_clen
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_clen($id_rodina,$id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  query("DELETE FROM tvori WHERE id_rodina=$id_rodina AND id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'','d','osoba',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','kopie',$id_orig)");
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_rodina
# zamění všechny výskyty kopie za originál v POBYT, TVORI, DAR, PLATBA, MAIL a kopii smaže
function elim2_rodina($id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  if ( $id_orig!=$id_copy ) {
    $now= date("Y-m-d H:i:s");
    query("UPDATE pobyt  SET i0_rodina=$id_orig WHERE i0_rodina=$id_copy");
    query("UPDATE tvori  SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    query("UPDATE dar    SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    query("UPDATE platba SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // smazání kopie
    query("UPDATE rodina SET deleted='D rodina=$id_orig' WHERE id_rodina=$id_copy");
    // opravy v originálu
    $access_orig= select("access","rodina","id_rodina=$id_orig");
    $access_copy= select("access","rodina","id_rodina=$id_copy");
    $access= $access_orig | $access_copy;
    query("UPDATE rodina SET access=$access WHERE id_rodina=$id_orig");
    // zápis o ztotožnění rodin do _track jako op=d (duplicita)
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_orig,'','d','orig',$id_copy)");
    // zápis o smazání kopie do _track jako op=x (eXtract)
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_copy,'','x','kopie',$id_orig)");
  }
  // odstranění duplicit v tabulce TVORI
  $qt= mysql_qry("
    SELECT COUNT(*) AS _n,GROUP_CONCAT(id_tvori ORDER BY id_tvori) AS _ids FROM tvori
    WHERE id_rodina=$id_orig GROUP BY id_osoba,role HAVING _n>1");
  while (($t= mysql_fetch_object($qt))) {
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
function elim2_data_osoba($ido) {  trace();
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
# -------------------------------------------------------------------------------- elim2_data_rodina
# načte data RODINA včetně záznamů v _track
function elim2_data_rodina($idr) {  trace();
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
/** ========================================================================================> SYSTEM **/
# -----------------------------------------------------------------------------==> db2_sys_transform
# transformace na schema 2015
# par.cmd = seznam transformací
# par.akce = id_akce | 0
# par.pobyt = id_pobyt | 0
function db2_sys_transform($par) { trace();
  global $ezer_root;
  $html= '';
  $updated= 0;
  $fs= array(
    // aplikace
    'akce' =>    array('access','id_duakce','id_hlavni'),
    'g_akce' =>  array(         'id_gakce'),
    'cenik' =>   array(         'id_cenik','id_akce'),
    'dar' =>     array(         'id_dar','id_osoba','id_rodina'),
    'dopis' =>   array(         'id_dopis','id_duakce','id_mailist'),
    'join_akce'=>array(         'id_akce'),
    'mailist' => array(         'id_mailist'),
    'osoba' =>   array('access','id_osoba'),
    'platba' =>  array(         'id_platba','id_osoba','id_rodina','id_duakce','id_pokl'),
    'pobyt' =>   array(         'id_pobyt','id_akce','i0_rodina'),
    'rodina' =>  array('access','id_rodina'),
    'spolu' =>   array(         'id_spolu','id_pobyt','id_osoba'),
    'tvori' =>   array(         'id_tvori','id_rodina','id_osoba'),
    // systém
    '_user' =>  array('id_user'),
    '_track' => array('id_track','klic'),
  );
  foreach (explode(',',$par->cmd) as $cmd ) {
    $update= false;
    $limit= "LIMIT 3";
    $db= 'ezer_fa';
    $ok= 1;
    switch ($cmd ) {
    // ---------------------------------------------- import: clear
    // vyčistí databázi ezer_db2, založí uživatele GAN
    case 'imp_clear':
      // vyprázdnění tabulek
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("TRUNCATE TABLE ezer_db2.$tab");
        if ( $ok ) $ok= mysql_qry("ALTER TABLE ezer_db2.rodina
          CHANGE ulice ulice tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER fotka,
          CHANGE obec obec tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER psc");
        if ( $ok ) {
          $html.= "<br>$tab: vymazáno";
        }
        if ( $ok ) $ok= mysql_qry("INSERT INTO _skill (skill_abbr, skill_desc) VALUES ('d', 'DB2')");
      }
      break;
    // ---------------------------------------------- import: YS
    // provede import z ezer_ys=>ezer_db (klíče na dvojnásobek+1 pro nenulové,access=1)
    case 'imp_YS':
      $db= 'ezer_ys';
    // ---------------------------------------------- import: FA
    // provede import z ezer_fa=>ezer_db (klíče na dvojnásobek,access=2)
    case 'imp_FA':
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
        if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._tmp_ LIKE $db.$tab");
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._tmp_ SELECT * FROM $db.$tab");
        if ( $ok ) {
          $updt= ''; $main= '';
          foreach ($keys as $key) {
            if ( $key=='access' ) {
              $updt.= ($cmd=='imp_FA' ? ',access=2':',access=1');
            }
            else {
              $updt.= $cmd=='imp_FA' ? ",$key=$key*2" : ",$key=IF($key,$key*2+1,0)";
              $main= $main ?: $key;
            }
          }
          $updt= substr($updt,1);
          $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY $main DESC");
          $nr= mysql_affected_rows();
        }
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
        if ( $ok ) {
          $html.= "<br>$tab: vloženo $nr záznamů";
        }
        mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      }
      break;
    // ---------------------------------------------- import: clear
    // vyčistí databázi ezer_db2, založí uživatele GAN
    case 'imp_user':
      // výmaz GAN/1,2 a ZMI/1
      if ( $ok ) $ok= mysql_qry("DELETE FROM ezer_db2._user
        WHERE abbr='GAN' OR (abbr='ZMI' AND skills LIKE '% y %')");
      // nový uživatel GAN
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._user
        (id_user,abbr,username,password,state,org,access,forename,surname,skills) VALUES
        (1,'GAN','gandi','radost','+-Uu',1,3,'Martin','Šmídek',
          'a ah f fa faa faa+ faa:c faan fad fae fam fam famg fams d m mg r sp spk spv test')");
      //  úprava skill a access pro MSM a ZMI
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=1,access=1,skills=CONCAT('d ',skills) WHERE abbr='MSM'");
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=2,access=2,skills=CONCAT('d ',skills) WHERE abbr='ZMI'");
      // doplnit skill d a sjednotit FA a YS skills
      if ( $ok ) {
        if ( !select('COUNT(*)','ezer_db2._skill',"skill_abbr='d'") ) {
          $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc) VALUES ('d', 'DB2')");
        }
        $qs= mysql_qry("SELECT skill_abbr, skill_desc FROM ezer_ys._skill");
        while ( $qs && ($s= mysql_fetch_object($qs)) ) {
          if ( !select('COUNT(*)','_skill',"skill_abbr='{$s->skill_abbr}'") ) {
            $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc)
                            VALUES ('{$s->skill_abbr}', '{$s->skill_desc}')");
          }
        }
      }
      break;
    default:
      fce_error("transformaci $cmd neumím");
    }
  }
  return $html;
}
?>
