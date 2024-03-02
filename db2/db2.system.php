<?php # (c) 2009-2024 Martin Smidek <martin@smidek.eu>
# =======================================================================> db2 kontrola a oprava dat
# --------------------------------------------------------------------------------- db2 kontrola_dat
# kontrola vazby rodina-tvori-osoba
function db2_kontrola_tvori($par) { trace();
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
          LEFT JOIN rodina AS r USING (id_rodina)
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
          LEFT JOIN rodina AS r USING (id_rodina)
          LEFT JOIN osoba AS o USING (id_osoba)
          WHERE r.deleted='' AND o.deleted=''
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

end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# --------------------------------------------------------------------------------- db2 kontrola_dat
# kontrola vazby pobyt-spolu-osoba
function db2_kontrola_spolu($par) { trace();
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

end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# --------------------------------------------------------------------------------- db2 kontrola_dat
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
  // -------------------------------------------==> .. fantómová osoba
  $msg= '';
  $rx= pdo_qry("
    SELECT o.id_osoba,id_dar,id_platba,s.id_spolu,p.id_pobyt,a.id_duakce,
      a.nazev,id_tvori,r.id_rodina,r.nazev,t.role /*,o.* */
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
