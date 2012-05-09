<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
# ================================================================================================== ALBUM
# -------------------------------------------------------------------------------------------------- album_set
# přidá fotografii do alba
function album_set($id_rodina,$fileinfo,$nazev) {
  global $ezer_path_root;
  $nazev= utf2ascii($nazev);
  $name= "$nazev.{$fileinfo->name}";
  $path= "$ezer_path_root/fotky/$name";
  $data= $fileinfo->text;
//                         $bytes= file_put_contents("$path.dmp",$data);  // debug info
  // test korektnosti fotky
  if ( substr($data,0,23)=="data:image/jpeg;base64," ) {
    // uložení fotky
    $data= base64_decode(substr("$data==",23));
//                          debug($fileinfo,"album_set ".strlen($fileinfo->text)."/".strlen($data));
    $bytes= file_put_contents($path,$data);
    // nalezení rodiny a poznamenání názvu fotky
    query("UPDATE rodina SET fotka='$name' WHERE id_rodina=$id_rodina");
  }
  else {
    $name= '';          // tiché oznámení chyby
  }
  return $name;
}
# -------------------------------------------------------------------------------------------------- album_delete
# zruší fotografii z alba
function album_delete($id_rodina) { #trace();
  global $ezer_path_root;
  $ok= 0;
  // nalezení rodiny a poznamenání názvu fotky
  $name= select('fotka','rodina',"id_rodina=$id_rodina");
//                                         display("name=$name");
  if ( $name ) {
    query("UPDATE rodina SET fotka='' WHERE id_rodina=$id_rodina");
    // smazání fotky
    $ok= unlink("$ezer_path_root/fotky/$name");
//                                         display("unlink('$ezer_path_root/fotky/$name')=$ok");
    $ok&= unlink("$ezer_path_root/fotky/copy/$name");
//                                         display("unlink('$ezer_path_root/fotky/copy/$name')=$ok");
  }
  return $ok;
}
# -------------------------------------------------------------------------------------------------- album_get
# zobrazí fotografii z alba
function album_get($name,$w,$h) { #trace();
  global $ezer_path_root;
  if ( $name ) {
    $dest= "$ezer_path_root/fotky/copy/$name";
    $orig= "$ezer_path_root/fotky/$name";
    if ( !file_exists($dest) ) {
      // zmenšení na požadovanou velikost
      $ok= album_resample($orig,$dest,$w,$h,0,1);
    }
    $src= "fotky/copy/$name";
    $html= "<a href='fotky/$name' target='_album'><img src='fotky/copy/$name'
      onload='var x=arguments[0];img_filter(x.target,\"sharpen\",1,2);'/></a>";
  //   $data= "iVBORw0"."KGgoAAAANSUhEUgAAACAAAAAFCAYAAAAkG+5xAAAABGdBTUEAANbY1E9YMgAAABl0RVh0U29mdHdhcm"
  //        . "UAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABTSURBVHjarJJRCgAgCEM9nHfy+K+fKBKzjATBDZUxFUAuU5mhnWPT"
  //        . "SzK7YCkkQR3tsM5bImjgVwE3HIED6vFvB4w17CC4dILdD5AIwvX5OW0CDAAH+Qok/eTdBgAAAABJRU5E"."rkJggg";
  //   $html= "<img alt='Embedded Image' src='data:image/png;base64,$data' />";
  }
  else {
    $html.= "Fotografie ještě není k dispozici - lze ji sem přidat myší";
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- album_resample
function album_resample($source, $dest, &$width, &$height,$copy_bigger=0,$copy_smaller=1) { #trace();
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
# ================================================================================================== KONTROLY
# -------------------------------------------------------------------------------------------------- akce_data_note_pece
# doplnění poznámek z ms_pece do osoba
function akce_data_note_pece($par) { trace();
  $html= '';
  $qry= "SELECT id_osoba,cislo,krestni,ms_pece.jmeno,poznamka,note FROM ms_pece
         JOIN osoba ON origin='c' AND prijmeni=ms_pece.jmeno AND osoba.jmeno=krestni
         WHERE cislo NOT IN (504,505,473) AND ms_pece.jmeno!=''  ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $set= "id_dupary={$x->cislo}";
    if ( $x->poznamka ) {
      $pozn= $x->note ? "{$x->poznamka}, {$x->note}" : $x->poznamka;
      $pozn= mysql_real_escape_string($x->poznamka);
      $set.= ",note='$pozn'";
    }
    mysql_qry("UPDATE osoba SET $set WHERE id_osoba={$x->id_osoba} ");
  }
  $html.= "hotovo";
  return $html;
}
# -------------------------------------------------------------------------------------------------- akce_kontrola_dat
# kontrola dat
#  -  nulové klíče
function akce_kontrola_dat($par) { trace();
  $html= '';
  $n= 0;
  $opravit= $par->opravit ? true : false;
  // kontrola nenulovosti klíčů ve spojovacích záznamech
  // tabulka SPOLU I
  $msg= '';
  $cond= "id_pobyt=0 OR spolu.id_osoba=0 ";
  $qry=  "SELECT id_spolu,spolu.id_osoba,spolu.id_pobyt,a.nazev,prijmeni,jmeno
          FROM spolu
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=p.id_osoba
          WHERE $cond";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= mysql_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam spolu={$x->id_spolu} je nulový$ok</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu spolu={$x->id_spolu} pobytu={$x->id_pobyt} akce {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>pobyt=0 v záznamu spolu={$x->id_spolu} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  // tabulka SPOLU II
  $qry=  "SELECT id_spolu,count(*) AS _pocet_,s.id_osoba,o.id_osoba,nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_pobyt HAVING _pocet_>1
          ORDER BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= mysql_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu}",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>násobný pobyt záznamem spolu={$x->id_spolu} na akci {$x->nazev}
      osoby {$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // tabulka TVORI I
  $msg= '';
  $cond= "tvori.id_rodina=0 OR tvori.id_osoba=0 ";
  $qry=  "SELECT id_tvori,role,tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          WHERE $cond ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      mysql_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam tvori={$x->id_tvori} je nulový</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu tvori={$x->id_spolu} rodiny={$x->id_rodina} {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>rodina=0 v záznamu tvori={$x->id_tvori} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  // tabulka TVORI II
  $qry=  "SELECT id_tvori,count(*) AS _pocet_,GROUP_CONCAT(role) AS _role_,
            tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          GROUP BY id_osoba,id_rodina HAVING _pocet_>1
          ORDER BY id_rodina ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit && strlen($x->_role_)==1 ) {
      mysql_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>násobné členství záznamem tvori={$x->id_tvori} v rodině {$x->nazev}
      osoby {$x->prijmeni} {$x->jmeno} v roli {$x->_role} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# ================================================================================================== PDF
# -------------------------------------------------------------------------------------------------- akce_platby
# nalezení platby za pobyt na akci
function akce_platby($id_pobyt) {  trace();
  $suma= 0;
  $html.= '';
  $id_akce= select("id_akce","pobyt","id_pobyt=$id_pobyt");
  // procházení osob
  $qo= "SELECT id_osoba FROM spolu WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    // hledání plateb této osoby
    $qp= "SELECT * FROM platba WHERE id_duakce=$id_akce AND id_osoba={$o->id_osoba}";
    $rp= mysql_qry($qp);
    while ( $rp && ($p= mysql_fetch_object($rp)) ) {
      $den= sql_date1($p->datum);
      $html.= "$den {$p->castka}Kč: {$p->poznamka}, {$p->ucet_nazev}";
      $suma+= $p->castka;
    }
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- akce_pobyt_default
# definice položek v POBYT podle počtu a věku účastníků
function akce_pobyt_default($id_pobyt,$zapsat=0) {  trace();
  // projítí společníků v pobytu
  $dosp= $deti= $koje= $noci= $sleva= $fce= 0;
  $msg= '';
  $qo= "SELECT o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce
        FROM spolu AS s
        JOIN osoba AS o USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    $noci= $o->_noci;
    $fce= $o->funkce;
    $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
    $msg.= " {$o->jmeno}:$vek";
    if     ( $vek<3  ) $koje++;
    elseif ( $vek<12 ) $deti++;
    else               $dosp++;
  }
  // zápis do pobytu
  if ( $zapsat ) {
    query("UPDATE pobyt SET luzka=".($dosp+$deti).",kocarek=$koje,strava_cel=$dosp,strava_pol=$deti,
             pocetdnu=$noci WHERE id_pobyt=$id_pobyt");
  }
  $ret= (object)array('luzka'=>$dosp+$deti,'kocarek'=>$koje,'pocetdnu'=>$noci,
                      'strava_cel'=>$dosp,'strava_pol'=>$deti,'vzorec'=>$fce);
                                                debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
  return $ret;
}
# -------------------------------------------------------------------------------------------------- akce_vzorec
# výpočet platby za pobyt na akci
function akce_vzorec($id_pobyt) {  trace();
  $id_akce= 0;
  $ok= true;
  $ret= (object)array('navrh'=>'cenu nelze spočítat');
  // parametry pobytu
  $x= (object)array();
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= mysql_qry($qp);
  if ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $sleva= $p->sleva;
    $neprijel= $p->funkce==10;
    $datum_od= $p->datum_od;
  }
  // podrobné parametry
  $deti= $koje= 0;
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    if ( $o->role=='d' ) {
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      if ( $vek<3 ) $koje++;
      else $deti++;
    }
  }
//                                                         debug($x,"pobyt");
  // zpracování strav
  $strava= akce_strava_pary($id_akce,'','','',true,$id_pobyt);
                                                        debug($strava,"strava");
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidel->$jidlo+= $pocet;
  }
//                                                         debug($jidel,"strava");
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= mysql_qry($qry);
  if ( $res && $c= mysql_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
  }
//                                                         debug($vzor);
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  $n= $ra ? mysql_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    while ( $ra && ($a= mysql_fetch_object($ra)) ) {
      $cc= (object)array();
      $cc->txt= $a->polozka;
      $cc->za= $a->za;
      $cc->c= $a->cena;
      $cc->j= $a->za ? $jidel->{$a->za} : '';
      $cenik[]= $cc;
    }
  }
  // výpočty
  if ( $ok ) {
    $nl= $x->nocoluzka;
    $u= $ucastniku;
    $cena= 0;
    $html.= "<table>";
    // ubytování
    $html.= "<tr><th>ubytování</th></tr>";
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
        case 'N':
          $cc= $nl * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
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
          $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          break;
        case 'Pd':
          if ( $deti ) {
            $cc= $a->c * $deti;
            $cena+= $cc;
            $ret->c_program+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'Pk':
          if ( $koje ) {
            $cc= $a->c * $koje;
            $cena+= $cc;
            $ret->c_program+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_program}</th></tr>";
    }
    // případné slevy
    $ret->c_sleva= 0;
    if ( !$neprijel && ($sleva!=0 || isset($vzor->slevy->procenta)) ) {
      $html.= "<tr><th>slevy</th></tr>";
      if ( $sleva!=0 ) {
        $cena-= $sleva;
        $ret->c_sleva-= $sleva;
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
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_sleva}</th></tr>";
    }
    $html.= "<tr><th>celková cena</th><td></td><th align='right'>$cena</th></tr>";
    $html.= "</table>";
    $ret->navrh= $html;
    $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
  }
  return $ret;
}
# ================================================================================================== PDF
# -------------------------------------------------------------------------------------------------- akce_pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka do PDF
function akce_pdf_stravenky($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs, $EZER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $x= akce_sestava($akce,$par,$title,$vypis,true);
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
            $parss[$n]->rect=  "<b>{$cp[$velikost]}</b>";
            $parss[$n]->ram= $parss[$n]->end= '';
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
                                        debug($parss,"akce_pdf_stravenky");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
function akce_pdf_prijem($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= akce_sestava($akce,$par,$title,$vypis,true);
//                                         debug($tab,"akce_sestava($akce,...)"); //return;
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
# -------------------------------------------------------------------------------------------------- akce_pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function akce_pdf_stitky($cond,$report_json) { trace();
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
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
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
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_rep_ids
# LOCAL
# vytvoření dopisů se šablonou pomocí TCPDF podle parametrů
# $parss  - pole obsahující substituce parametrů pro $text
# vygenerované dopisy ve tvaru souboru PDF se umístí do ./docs/$fname
# případná chyba se vrátí jako Exception
function dop_rep_ids($report_json,$parss,$fname) { trace();
  global $json;
  $err= 0;
  // transformace $parss pro strtr
  $subst= array();
  for ($i=0; $i<count($parss); $i++) {
    $subst[$i]= array();
    foreach($parss[$i] as $x=>$y) {
      $subst[$i]['{'.$x.'}']= $y;
    }
  }
  $report= $json->decode($report_json);
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
                                                        debug($texty,'dop_rep_ids');
  tc_report($report,$texty,$fname);
}
# ================================================================================================== SYSTEM-DATA
# -------------------------------------------------------------------------------------------------- akce_foxpro_data
# dokončení transformace z my_mysql.prg naplněním id_pary
function akce_foxpro_data() {  #trace('');
/*
  $n= 0;
  // přidání id_pary
  $qry= "SELECT id_pary,cislo FROM ms_pary ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_deti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_kursdeti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
  }
  $html= "Do tabulek ms_kurs, ms_deti, ms_kursdeti byly {$n}x přidány hodnoty klíče id_pary";
  // přidání id_akce
  $n= 0;
  $qry= "SELECT id_akce,source,akce FROM ms_akce ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
    mysql_qry("UPDATE ms_kursdeti SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
    mysql_qry("UPDATE uakce SET id_akce={$x->id_akce} WHERE ms_akce={$x->akce} AND ms_source='{$x->source}'");
  }
  $html.= "<br>Do tabulek ms_kurs, ms_kursdeti, uakce byly {$n}x přidány hodnoty klíče id_akce";
  // oprava dětí
  $n= 0;
  $qry= "SELECT id_deti,id_pary,jmeno FROM ms_deti ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_deti do ms_kursdeti
    mysql_qry("UPDATE ms_kursdeti SET id_deti={$x->id_deti} WHERE id_pary={$x->id_pary} AND jmeno='{$x->jmeno}'");
  }
  $html.= "<br>Do tabulky ms_kursdeti byly {$n}x přidány hodnoty klíče id_deti";
  return $html;
*/
  // verze pro YMCA Familia
  $n= 0;
  // přidání id_pary
  $qry= "SELECT id_pary,cislo FROM ms_pary ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_deti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_kursdeti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
  }
  $html= "Do tabulek ms_kurs, ms_deti, ms_kursdeti byly {$n}x přidány hodnoty klíče id_pary";
  // přidání id_akce
  $n= 0;
  $qry= "SELECT id_akce,source,akce FROM ms_akce ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
    mysql_qry("UPDATE ms_kursdeti SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
//     mysql_qry("UPDATE uakce SET id_akce={$x->id_akce} WHERE ms_akce={$x->akce} AND ms_source='{$x->source}'");
  }
  $html.= "<br>Do tabulek ms_kurs, ms_kursdeti, uakce byly {$n}x přidány hodnoty klíče id_akce";
  // oprava dětí
  $n= 0;
  $qry= "SELECT id_deti,id_pary,jmeno FROM ms_deti ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_deti do ms_kursdeti
    mysql_qry("UPDATE ms_kursdeti SET id_deti={$x->id_deti} WHERE id_pary={$x->id_pary} AND jmeno='{$x->jmeno}'");
  }
  $html.= "<br>Do tabulky ms_kursdeti byly {$n}x přidány hodnoty klíče id_deti";
  return $html;
}
# ================================================================================================== VÝPISY
# výběr generátoru sestavy
# -------------------------------------------------------------------------------------------------- akce_sestava2
# generování sestav
#   $typ = j | p | vp | vs | vn | vv | vj | sk | sd | d | fs
function akce_sestava($akce,$par,$title,$vypis,$export=false) {
  return $par->typ=='p'  ? akce_sestava_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='j'  ? akce_sestava_lidi($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vp' ? akce_vyuctov_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vs' ? akce_strava_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vj' ? akce_stravenky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vn' ? akce_sestava_noci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vv' ? akce_text_vyroci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='sk' ? akce_skupinky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='sd' ? akce_skup_deti($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='d'  ? akce_sestava_pecouni($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='fs' ? akce_fotoseznam($akce,$par,$title,$vypis,$export)
                         : fce_error("akce_sestava: N.Y.I.") ))))))))));
}
# -------------------------------------------------------------------------------------------------- akce_table
function akce_table($tits,$flds,$clmn,$export=false) {
  $result= (object)array();
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
        $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>$n řádků</div>";
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_fotoseznam
# generování HTML kódu pro zobrazování fotek na CD akce
function akce_fotoseznam($akce,$par,$title,$vypis,$export=false) { trace();
  global $ezer_path_root;
  $result= (object)array();
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $fotky= "";
  // získání seznamu fotek a jmen
  $qry=  "SELECT
            p.pouze as pouze, r.nazev AS nazev,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            r.fotka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cnd AND fotka!=''
          GROUP BY id_pobyt
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    $fotka=    $x->fotka;
    $fotky.= "\n      '$fotka','$prijmeni $jmena',";
  }
  // generování skriptu
  $script= <<<__EOD
    <script language='JavaScript'>
      var portrety= [
        $fotky
        0
      ];
      var portret= portrety.length/2;
      function showNextP() {
        if (portret<portrety.length-2) portret+= 2;
        showPortret(portret);
      }
      function showPrevP() {
        if (portret>0) portret-= 2;
        showPortret(portret);
      }
      function showPortret(f) {
        portret= f; image= portrety[f]; text= portrety[f+1];
        portret_img.src="fotky/" + image;
        portret_title.innerHTML= "<b><big>" + text + "</big></b>";
        portret_number.innerHTML= "<b>" + (1+f/2) + "/" + (portrety.length/2) + "</b>";
        portret_prior.src= "img/left.gif"; portret_next.src= "img/right.gif";
        if (portret==0) portret_prior.src= 'img/clear.gif';
        if (portret==portrety.length-2) portret_next.src= 'img/clear.gif' ;
      }
      function showThumbP(f) {
        image= portrety[f];
        text= "<font color=#d0b090>" + portrety[f+1] + "</font>";
        document.write("<img border=0  src='fotky/copy/" + image + "' "
        + " onClick=\"showPortret(" + f + ");\" "
        + "  style='cursor:pointer;' title='klikni pro zvětšení'><div style='width:120px;text-align:center'>"
        + text+"</div>");
      }
    </script>
    <table height=640 border=0 style="background-color:#aa6633;color:#004080">
     <tr>
      <td width=165 align=center>
        <div style="overflow:auto;height:630px;width:155px;background-image:url('img/film120b.gif')">
          <script><!--
            for (f=0;f<portrety.length;f+=2) showThumbP(f);
          --></script>
        </div>
      </td>
      <td width=820 valign=top>
        <table width=820 style='color:#004080;font-weight:bold;' border=0>
         <tr>
          <td width=420 id='portret_title'></td>
          <td width=30><img  id=portret_prior src='img/left.gif' style="cursor:pointer"
            onClick="javascript:showPrevP();"></td>
          <td width=60 id=portret_number align=center>n/m</td>
          <td width=30><img  id=portret_next src='img/right.gif' style="cursor:pointer"
            onClick="javascript:showNextP();"></td>
         </tr>
         <tr>
          <td width=540 colspan=4>
            <a href=#><img border=0 name='portret_img' class=image></a>
            <script>showPortret(portret);</script>
          </td>
         </tr>
        </table>
      </td>
     </tr>
    </table>
__EOD;
  $fname= 'fotoseznam'.date("-Ymd_Hi").'.htm';
  $path= "$ezer_path_root/docs/$fname";
  file_put_contents($path,$script);
  $result->html= "Tento skript lze stáhnout <a href='docs/$fname' target='fotoseznam'>zde</a>.
    Fotky je třeba resamplovat na velikost 800x600 a miniatury na 120x90 <br><br> $script";
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.ulice,o.psc,o.obec,o.telefon,o.email,
            s.skupinka as skupinka,
            IF(o.note='' AND s.poznamka='','',CONCAT(o.note,' / ',s.poznamka)) AS _poznamky
          FROM ezer_fa.pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.funkce=99 AND p.id_akce='$akce' AND $cnd
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $clmn[$n][$f]= $x->$f;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- akce_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT
          p.pouze,p.poznamka,
          o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,
          r.ulice,r.psc,r.obec,r.telefony,r.emaily
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          JOIN tvori AS t ON t.id_osoba=o.id_osoba
          JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cnd
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $clmn[$n][$f]= $x->$f;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- akce_sestava_pary
# generování sestavy pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_pary($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT
            r.nazev as nazev,p.pouze as pouze,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.email,'')    SEPARATOR '') as email_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.telefon,'')  SEPARATOR '') as telefon_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.email,'')    SEPARATOR '') as email_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.telefon,'')  SEPARATOR '') as telefon_z,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti,
            SUM(IF(t.role='d',1,0)) as _deti,
            r.ulice,r.psc,r.obec,r.telefony,r.emaily,p.poznamka,
            p.skupina,p.pokoj,p.luzka,p.kocarek,p.pristylky,p.strava_cel,p.strava_pol
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cnd
          GROUP BY id_pobyt $hav
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    $x->_pocet= ($x->pouze?" 1":" 2").($x->_deti?"+{$x->_deti}":'');
    // emaily
    $a_emaily_m= preg_split("/,\s*|;\s*/",trim($x->email_m ? $x->email_m : $x->emaily," ,;"));
    $a_emaily_z= preg_split("/,\s*|;\s*/",trim($x->email_z ? $x->email_z : $x->emaily," ,;"));
    $a_emaily= preg_split("/,\s*|;\s*/",trim($x->emaily," ,;"));
    $emaily= implode(';',array_diff(array_unique(array_merge($a_emaily,$a_emaily_m,$a_emaily_z)),array('')));
    $emaily_m= implode(';',$a_emaily_m);
    $emaily_z= implode(';',$a_emaily_z);
    $x->emaily= $x->pouze==1 ? $emaily_m  : ($x->pouze==2 ? $emaily_z : $emaily);
    $x->emaily.= $x->emaily ? ';' : '';
    // telefony
    $a_telefony_m= preg_split("/,\s*|;\s*/",trim($x->telefon_m ? $x->telefon_m : $x->telefony," ,;"));
    $a_telefony_z= preg_split("/,\s*|;\s*/",trim($x->telefon_z ? $x->telefon_z : $x->telefony," ,;"));
    $a_telefony= preg_split("/,\s*|;\s*/",trim($x->telefony," ,;"));
    $telefony= implode(';',array_diff(array_unique(array_merge($a_telefony,$a_telefony_m,$a_telefony_z)),array('')));
    $telefony_m= implode(';',$a_telefony_m);
    $telefony_z= implode(';',$a_telefony_z);
    $x->telefony= $x->pouze==1 ? $telefony_m  : ($x->pouze==2 ? $telefony_z : $telefony);
    // další
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $clmn[$n][$f]= $x->$f;
    }
//     break;
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# ================================================================================================== TEXTY
# -------------------------------------------------------------------------------------------------- akce_text_vyroci
function akce_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $qry=  "SELECT
          prijmeni,jmeno,narozeni,role
          FROM akce AS a
          JOIN pobyt AS p ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE a.id_duakce='$akce' AND
            CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do
          ORDER BY SUBSTR(narozeni,5,6) ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $qry=  "SELECT
            r.nazev,datsvatba,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z
          FROM akce AS a
          JOIN pobyt AS p ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE a.id_duakce='$akce' AND pouze=0 AND
            CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do
          GROUP BY id_pobyt
          ORDER BY SUBSTR(datsvatba,5,6) ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['s'][]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}|".sql_date1($x->datsvatba);
  }
  // redakce
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
  if ( count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  $result->html= $html;
  return $result;
}
# ================================================================================================== VYÚČTOVÁNÍ ETC.
# -------------------------------------------------------------------------------------------------- akce_sestava_noci
# generování sestavy přehledu člověkonocí pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce_sestava_noci($akce,$par,$title,$vypis,$export=false) { trace();
  // definice sloupců
  $tit= "Manželé:25,pokoj:8:r,dnů:5:r,nocí:5:r,lůžek:5:r:s,lůžko nocí:5:r:s,přis týlek:5:r:s,přis týlko nocí:5:r:s";
  $fld= "manzele,pokoj,pocetdnu,=noci,luzka,=luzkonoci,pristylky,=pristylkonoci";
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
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
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
            pokoj,luzka,pristylky,pocetdnu,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            p.pouze,r.nazev as nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9,10) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
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
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
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
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_stravenky
# generování stravenek účastníky $akce - rodinu
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz, zena a děti
# generované vzorce
#   platit = součet předepsaných plateb
function akce_stravenky($akce,$par,$title,$vypis,$export=false) { #trace();
//                                                         debug($par,"akce_stravenky($akce,,$title,$vypis,$export)");
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= $par->cnd;
  $html= '';
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé:25";
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
  // data akce
  $akce_data= (object)array();
  $dny= array('ne','po','út','st','čt','pá','so');
  $qry=  "SELECT r.nazev as nazev,strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 5";
  $res= mysql_qry($qry);
  // stravenky - počty po dnech
  $str= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=datum,jídlo=s|o|v, typ=c|p
  // s uvážením $oo='sv' - první jídlo prvního dne a poslední jídlo posledního dne
  $jidlo= array('s','o','v');
  $xjidlo= array('s'=>0,'o'=>1,'v'=>2);
  $jidlo_1= $xjidlo[$oo[0]];
  $jidlo_n= $xjidlo[$oo[1]];
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $akce_data->nazev= $x->akce_nazev;
    $akce_data->rok=   $x->akce_rok;
    $akce_data->misto= $x->akce_misto;
    $str_kdo= array();
    $clmn[$n]= array();
    $clmn[$n]['manzele']=
          $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
       : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
       : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
    // stravy
    $sc= $x->strava_cel;
    $sp= $x->strava_pol;
    $csc= $x->cstrava_cel;
    $csp= $x->cstrava_pol;
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
      }
    }
    $kdo= ($x->pouze==1 ? "{$x->prijmeni_m}|{$x->jmeno_m}"
        : ($x->pouze==2 ? "{$x->prijmeni_z}|{$x->jmeno_z}"
        : "{$x->nazev}|{$x->jmeno_m} a {$x->jmeno_z}"));
    $str[$kdo]= $str_kdo;
  }
                                                        debug($str,"stravenky");
//                                                         debug($suma,"sumy");
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
  $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->html.= "</br>";
  $result->href= $href;
  $result->tab= $str;
  $result->akce= $akce_data;
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_strava_pary
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce_strava_pary($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé:25";
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
                                                        display($tit);
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
  // data akce
  if ( $id_pobyt )
    $cond.= " AND id_pobyt=$id_pobyt";
  $qry=  "SELECT r.nazev as nazev,strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 5";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $clmn[$n]['manzele']=
          $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
       : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
       : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
    // stravy
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
//                                                         debug($suma,"sumy");
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_vyuctov_pary
# generování sestavy vyúčtování pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce_vyuctov_pary($akce,$par,$title,$vypis,$export=false) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Manželé:25"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:S,str. pol.:5:r:s"
      . ",platba ubyt.:7:r:s,platba strava:7:r:s,platba režie:7:r:s,sleva:7:r:s,CD:6:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:d"
      . ",nedo platek:6:r:s,pokladna:6:r:s,přepl.:6:r:s,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dar:7:r:s"
      . "";
  $fld= "manzele"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",platba1,platba2,platba3,platba4,=cd,=platit,platba,datplatby"
      . ",=nedoplatek,=pokladna,=preplatek,poznamka,spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,=dotace,=nedopl,=dar"
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
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,strava_cel,strava_pol,
            platba1,platba2,platba3,platba4,platba,datplatby,cd,p.poznamka,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
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
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 10";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.1;
    $DPH2= 0.2;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $preplatek= $x->platba > $predpis ? $x->platba - $predpis : '';
        $nedoplatek= $x->platba < $predpis ? $predpis - $x->platba : '';
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu-1);
                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]"; break;
        case '=preplatek':  $val= $preplatek;
                            $exp= "=IF([platba,0]>[=platit,0],[platba,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek; break;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=pokladna':   $val= ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
                            $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
        case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
                            $exp= "=[platba1,0]-[=ubyt,0]"; break;
        case '=strava':     $val= round($x->platba2/(1+$DPH2));
                            $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
        case '=stravaDPH':  $val= round($x->platba2*$DPH2/(1+$DPH2));
                            $exp= "=[platba2,0]-[=strava,0]"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=zaplaceno':  $val= 0+$x->platba;
                            $exp= "=[platba,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=dar':        $val= $preplatek;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
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
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ----------------------------------------------------- akce_sestava_td_style
# $fmt= r|d
function akce_sestava_td_style($fmt) {
  $style= array();
  switch ($fmt) {
  case 'r': $style[]= 'text-align:right'; break;
  case 'd': $style[]= 'text-align:right'; break;
  case '!': $style[]= 'color:red'; break;
  }
  return count($style)
    ? " style='".implode(';',$style)."'" : '';
}
# ================================================================================================== SKUPINKY DĚTÍ
# -------------------------------------------------------------------------------------------------- narozeni2roky_sql
# zjistí aktuální věk v rocích z data narození (typu mktime) zjištěného třeba rc2time          ?????
# pokud je předáno $now(jako timestamp) bere se věk k tomu
function narozeni2roky_sql($time_sql,$now_sql=0) {
  $time= sql2stamp($time_sql);
  $now= $now_sql ? sql2stamp($now_sql) : time();
  $roky= floor((date("Ymd",$now) - date("Ymd", $time)) / 10000);
  return $roky;
}
# -------------------------------------------------------------------------------------------------- akce_skup_deti
# tisk skupinek akce dětí
function akce_skup_deti($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  // celkový počet dětí na kurzu
  $qry= "SELECT SUM(IF(t.role='d',1,0)) AS _deti,SUM(IF(funkce=99,1,0)) AS _pecounu
         FROM akce AS a
         JOIN pobyt AS p ON a.id_duakce=p.id_akce
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
         WHERE id_duakce='$akce'
         GROUP BY id_duakce ";
  $res= mysql_qry($qry);
  $pocet= mysql_fetch_object($res);
//                                                         debug($pocet,"počty");
  // zjištění skupinek
  $skupiny= array();   // [ skupinka => [{fce,příjmení,jméno},....], ...]
  $qry="SELECT id_pobyt,skupinka,funkce,prijmeni,jmeno,narozeni,rc_xxxx,datum_od
        FROM osoba AS o
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce  AS a ON id_duakce='$akce'
        WHERE  id_akce='$akce' AND skupinka!=0
        ORDER BY skupinka,IF(funkce=99,0,1),prijmeni,jmeno ";
  $res= mysql_qry($qry);
  while ( $res && ($o= mysql_fetch_object($res)) ) {
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
# ================================================================================================== SKUPINKY
# -------------------------------------------------------------------------------------------------- akce_skupinky
# generování pomocných sestav pro tvorbu skupinek
#   $par->fce = plachta | prehled
function akce_skupinky($akce,$par,$title,$vypis,$export=false) {
  return $par->fce=='plachta'  ? akce_plachta($akce,$par,$title,$vypis,$export)
     : ( $par->fce=='prehled'  ? akce_skup_hist($akce,$par,$title,$vypis,$export)
     : ( $par->fce=='tisk'     ? akce_skup_tisk($akce,$par,$title,$vypis,$export)
                               : (object)array('html'=>'sestava ještě není hotova') ));
}
# -------------------------------------------------------------------------------------------------- akce_skup_check
# zjištění konzistence skupinek podle příjmení VPS/PPS
function akce_skup_check($akce) {
  return akce_skup_get($akce,1,$err);
}
# -------------------------------------------------------------------------------------------------- akce_skup_get
# zjištění skupinek podle příjmení VPS/PPS
function akce_skup_get($akce,$kontrola,&$err,$par=null) { trace();
  global $VPS;
  $msg= array();
  $skupiny= array();
  $celkem= select('count(*)','pobyt',"id_akce=$akce AND funkce IN (0,1,2)");
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
      WHERE p.id_akce=$akce AND skupina!=0
      GROUP BY skupina ";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    if ( $s->_n_svps==1 || $s->_n_vps==1 ) {
      $skupina= array();
      $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina})
          GROUP BY id_pobyt
          ORDER BY funkce DESC, nazev";
      $resu= mysql_qry($qryu);
      while ( $resu && ($u= mysql_fetch_object($resu)) ) {
        $mark= '';
        if ( $par && $par->mark=='novic' ) {
          // minulé účasti
          $muz= $u->id_osoba_m;
          $rqry= "SELECT count(*) as _pocet
                  FROM akce AS a
                  JOIN pobyt AS p ON a.id_duakce=p.id_akce
                  JOIN spolu AS s USING(id_pobyt)
                  WHERE a.druh=1 AND s.id_osoba=$muz AND p.id_akce!=$akce";
          $rres= mysql_qry($rqry);
          if ( $rres && ($r= mysql_fetch_object($rres)) ) {
            $mark= $r->_pocet;
          }
          $mark= $mark==0 ? '* ' : '';
        }
        $u->_nazev= "$mark {$u->nazev} {$u->jmeno_m} a {$u->jmeno_z}";
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
  // řazení - v PHP nelze udělat
  if ( count($all) ) {
    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE id_pobyt IN (".implode(',',$all).") ";
    $reso= mysql_qry($qryo);
    while ( $reso && ($o= mysql_fetch_object($reso)) ) {
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
  if ( $celkem!=$n ) {
    $msg[]= ($celkem-$n)." účastníků není zařazeno do skupinek";
    $err+= 1;
  }
  if ( count($msg) && !$kontrola )
    fce_warning(implode(",<br>",$msg));
  elseif ( !count($msg) && $kontrola )
    $msg[]= "Vše je ok";
  // konec
  return $kontrola ? implode(",<br>",$msg) : $skup;
}
# -------------------------------------------------------------------------------------------------- akce_skup_renum
# přečíslování skupinek podle příjmení VPS/PPS
function akce_skup_renum($akce) {
  $err= 0;
  $msg= '';
  $skupiny= akce_skup_get($akce,0,$err);
  if ( $err>1 ) {
    $msg= "skupinky nejsou dobře navrženy - ještě je nelze přečíslovat";
  }
  else {
    $n= 1;
    foreach($skupiny as $ivps=>$skupina) {
      $cleni= implode(',',array_keys($skupina));
      $qryu= "UPDATE pobyt SET skupina=$n WHERE id_pobyt IN ($cleni) ";
      $resu= mysql_qry($qryu);
//                                                         display("$n: $qryu");
      $n++;
    }
    $msg= "bylo přečíslováno $n skupinek";
  }
  return $msg;
}
# -------------------------------------------------------------------------------------------------- akce_skup_tisk
# tisk skupinek akce
function akce_skup_tisk($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  $html= "<table>";
  $skupiny= akce_skup_get($akce,0,$err,$par);
  $n= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
        $clmn[$n]['skupina']= $i==$c->id_pobyt ? $c->skupina : '';
        $clmn[$n]['jmeno']= $c->_nazev;
        $clmn[$n]['pokoj']= $i==$c->id_pobyt ? $c->pokoj : '';
        $n++;
      }
      $clmn[$n]['skupina']= $clmn[$n]['jmeno']= $clmn[$n]['pokoj']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,jméno:30,pokoj $VPS:10:r");
    $result->flds= explode(',',"skupina,jmeno,pokoj");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    foreach ($skupiny as $i=>$s) {
      $tab= "<table>";
      foreach ($s as $c) {
        if ( $i==$c->id_pobyt )
          $tab.= "<tr><th>{$c->skupina}</th><th>{$c->_nazev}</th><th>{$c->pokoj}</th></tr>";
        else
          $tab.= "<tr><td></td><td>{$c->_nazev}</td><td></td></tr>";
      }
      $tab.= "</table>";
      if ( $n%2==0 )
        $html.= "<tr><td>&nbsp;</td></tr><tr><td valign='top'>$tab</td>";
      else
        $html.= "<td valign='top'>$tab</td></tr>";
      $n++;
    }
    if ( $n%2==1 )
      $html.= "<td></td></tr>";
    $html.= "</table>";
    $result->html= $html;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_skup_hist
# přehled starých skupinek letního kurzu MS účastníků této akce
function akce_skup_hist($akce,$par,$title,$vypis,$export) { trace();
  $result= (object)array();
  $html= "<dl>";
  // letošní účastníci
  $letos= array();
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,p.funkce as funkce,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o město
  $old= 0; $old_nazev= '';
  foreach ($letos as $muz=>$info) {
    if ( $old_nazev==$info->_nazev ) {
      $letos[$old]->_nazev= $letos[$old]->nazev.' '.$letos[$old]->jmeno_m.' '.$letos[$old]->jmeno_z;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.' '.$letos[$muz]->jmeno_m.' '.$letos[$muz]->jmeno_z;
    }
    $old= $muz;
    $old_nazev= $info->_nazev;
  }
//                                                         debug($odkud);
  // tisk
  foreach ($letos as $muz=>$info) {
    // minulé účasti
    $n= 0;
    $qry= " SELECT p.id_akce,skupina,year(datum_od) as rok
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= mysql_qry($qry);
    $ucasti= '';
    while ( $res && ($r= mysql_fetch_object($res)) ) {
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
      $res_s= mysql_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= mysql_fetch_object($res_s)) ) if ( $s->id_osoba_m!=$muz ) {
        $spolu.= "$del{$letos[$s->id_osoba_m]->_nazev}";
        $del= ', ';
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}</u>: $spolu";
      }
    }
    if ( $ucasti )
      $html.= "<dt><b>{$info->_nazev}</b> $n&times;</dt><dd>$ucasti</dd>";
    elseif ( $n )
      $html.= "<dt><b>{$info->_nazev}</b> $n&times;</dt>";
    else
      $html.= "<dt><b>{$info->_nazev}</b> poprvé</dt>";
  }
  $html.= "</dl>";
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. <br>Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok.";
  $html= "<i>$note</i><br>$html";
  $result->html= $html;
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_plachta
# podklad pro tvorbu skupinek
function akce_plachta($akce,$par,$title,$vypis,$export=0) { trace();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $excel= array();
//   $html.= "<table class='vypis'>";
  // letošní účastníci
  $qry=  "SELECT
          r.nazev as jmeno,p.pouze as pouze,r.obec as mesto,svatba,p.funkce as funkce,
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
          ( SELECT COUNT(*)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS deti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
//   $qry.= " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    // minulé účasti
    $rqry= "SELECT count(*) as _pocet
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba=$muz AND p.id_akce!=$akce";
    $rres= mysql_qry($rqry);
    while ( $rres && ($r= mysql_fetch_object($rres)) ) {
      $u->ucasti= $r->_pocet ? "  {$r->_pocet}x" : '';
    }
    // věk
    $vek_m= sql2roku($u->narozeni_m);
    $vek_z= sql2roku($u->narozeni_z);
    $vek= abs($vek_m-$vek_z)<5 ? $vek_m : "$vek_m/$vek_z";
    // spolu
    $spolu= $u->svatba ? $letos-$u->svatba : '?';
    // děti
    $deti= $u->deti;
    // vzdělání
    $vzdelani_muze= mb_substr($c_vzdelani[$u->vzdelani_m],0,2,"UTF-8");
    $vzdelani_zeny= mb_substr($c_vzdelani[$u->vzdelani_z],0,2,"UTF-8");
    $vzdelani= $vzdelani_muze==$vzdelani_zeny ? $vzdelani_muze : "$vzdelani_muze/$vzdelani_zeny";
//                                                         display("$vek_m/$vek_z=$vek");
    // konfese
    $cirkev= $u->cirkev_m==$u->cirkev_z
      ? ($u->cirkev_m==1 ? '' : ", {$c_cirkev[$u->cirkev_m]}")
      : ", {$c_cirkev[$u->cirkev_m]}/{$c_cirkev[$u->cirkev_z]}";
    // agregace
    $r1= ($u->funkce==1||$u->funkce==2 ? '* ' : '')."{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z} {$u->ucasti}";
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
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m);
    }
  }
//                                                 debug($excel);
  if ( $export ) {
    $result->xhref= akce_plachta_export($excel,'plachta');
  }
  $result->html= $html;
  return $result;
}
// ---------------------------------------------- roku
// vrací zaokrouhlený počet roku od narození poteď
function sql2roku($narozeni) {
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($y,$m,$d)= explode('-',$narozeni);
    $now= time();
    $nar= mktime(0,0,0,$m,$d,$y)+1;
    $roku= floor(($now-$nar)/(60*60*24*365.24));
  }
  return $roku;
};
# -------------------------------------------------------------------------------------------------- akce_plachta_export
function akce_plachta_export($line,$file) { trace();
  require_once('./ezer2/server/licensed/xls/OLEwriter.php');
  require_once('./ezer2/server/licensed/xls/BIFFwriter.php');
  require_once('./ezer2/server/licensed/xls/Worksheet.php');
  require_once('./ezer2/server/licensed/xls/Workbook.php');
  global $ezer_path_root;
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
    $fields= explode(',','r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:8');
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
    }
    $wb->close();
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
    $html.= " <br>Vygenerovaným listem <b>Hodnoty</b> je třeba nahradit stejnojmenný list v sešitu";
    $html.= " <b>doc/plachta11.xls</b> a dále postupovat podle návodu v listu <b>Návod</b>.";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# ================================================================================================== BANKA
# -------------------------------------------------------------------------------------------------- akce_rb_urci
# pokus o určení plátce a účelu platby
function akce_rb_urci($vs,$ss,$datum) {  trace();
  $result= (object)array('id_rodina'=>0,'id_osoba'=>0,'tipy'=>'');
  // určení osoby a rodiny
  $tipy= array();
  $presne= false;
  $narozeni= rc2ymd($vs);
  $rc_xxxx= strlen($vs)==10 ? substr($vs,6,4) : '0000';
  $AND= strlen($vs)==10 ? " AND rc_xxxx='".substr($vs,6,4)."'" : '';
  $qry= "SELECT id_osoba,id_rodina,prijmeni,jmeno,rc_xxxx FROM osoba
         LEFT JOIN tvori AS t USING(id_osoba)
         WHERE narozeni='$narozeni' ";
  $res= mysql_qry($qry);
  $n= mysql_num_rows($res);
  if ( !$n ) {
    $html.= "plátce nenalezen";
  }
  else {
    while ( $res && $o= mysql_fetch_object($res) ) {
      if ( $o->rc_xxxx==$rc_xxxx ) {
        $presne= true;
        $html.= " {$o->prijmeni} {$o->jmeno} - {$o->rc_xxxx}";
        $result->id_rodina= $o->id_rodina;
        $result->id_osoba= $o->id_osoba;
        break;
      }
      $tipy[]= $o;
    }
    if ( !$presne ) {
      foreach($tipy as $o) {
        $result->tipy.= "{$o->id_osoba}|{$o->prijmeni} {$o->jmeno}|";
        $html.= "<br>{$o->id_osoba}:{$o->prijmeni} {$o->jmeno} - {$o->rc_xxxx}";
      }
    }
  }
  // určení akce podle SS a roku (zjednodušení)
  $rok= substr($datum,0,4);
  $qa= "SELECT id_duakce,g_kod,g_rok
        FROM ezer_ys.akce AS da2
        LEFT JOIN join_akce AS ja2 ON ja2.id_akce=da2.id_duakce
        LEFT JOIN g_akce AS ga2 USING(g_rok,g_kod)
        WHERE g_rok=$rok AND g_kod='$ss' ";
  $ra= mysql_qry($qa);
  $n= mysql_num_rows($ra);
  if ( $n==1 ) {
    $a= mysql_fetch_object($ra);
    $result->id_duakce= $a->id_duakce;
  }
  // konec
  $result->html= $html;
                                                debug($result,"akce_rb_urci($vs,$ss)");
  return $result;
}
# -------------------------------------------------------------------------------------------------- akce_rb_platby
# přečtení pohybů na transparentních účtech RB
function akce_rb_platby() {  trace();
  $html= '';
  $html.= akce_rb_ucet("514048001","M");
  $html.= "<br>";
  $html.= akce_rb_ucet("514048044","D");
//   $html.= "<br>";
//   $html.= akce_rb_ucet("514048052","D");
  return $html;
}
# -------------------------------------------------------------------------------------------------- akce_rb_ucet
# přečtení pohybů na transparentním účtu RB
function akce_rb_ucet($cislo,$nazev) {  trace();
  $n= 0;
  $html= '';
  $dom= new DOMDocument();
  $page= file_get_contents("http://www.rb.cz/firemni-finance/transparentni-ucty/?root=firemni-finance"
     . "&item1=transparentni-ucty&tr_acc=vypis&account_number=$cislo" );
  $ok= @$dom->loadHTML($page);
  if ( $ok ) {
    // kontrola hlavičky
    $thead= $dom->getElementsByTagName('thead');                // DOMNodeList
    if ( $thead->length ) {
      $trs= $thead->item(0)->getElementsByTagName('tr');        // DOMNode DOMNodeList
      for ($i= 0; $i < $trs->length; $i++) {
        $tds= $trs->item($i)->getElementsbyTagName('th');       // DOMNode DOMNodeList
        for ($j= 0; $j < $tds->length; $j++) {
          $typ= $tds->item($j)->nodeType;                       // DOMNode
          $wh[$i][$j]= dom_shownode($tds->item($j));
        }
      }
      // test hlavičky
      $head= array("Datum|Čas","Poznámky|název účtu","Datum odepsání|valuta|typ",
        "Variabilní symbol|konstantní symbol|specifický symbol","|částka|","Poplatek|směna|zpráva");
      $ok= true;
      for ($j= 0; $j<count($head); $j++) {
        if ($wh[0][$j]!=$head[$j] ) $ok= false;
      }
      if ( !$ok ) fce_warning("změna formátu hlavička tabulky");
    }
    else fce_warning("chybí hlavička tabulky");
  }
  if ( $ok ) {
    // datové řádky
    $tbody= $dom->getElementsByTagName('tbody');
    if ( $tbody->length ) {
      $trs= $tbody->item(0)->getElementsByTagName('tr');
      for ($i= 0; $i < $trs->length; $i++) {
        $n++;
        $tds= $trs->item($i)->getElementsbyTagName('td');
        for ($j= 0; $j < $tds->length; $j++) {
          $wh[$i+1][$j].= dom_shownode($tds->item($j));
        }
//                                                 break;
      }
    }
    else fce_warning("chybí tabulka");
//                                                 debug($wh,"platby",(object)array('html'=>1));
  }
  if ( $ok ) {
    $nove= 0; $lst= '';
    // zpracování tabulky
    for ($i= 1; $i<=$n; $i++) {
      list($datum,$cas)=                explode('|',$wh[$i][0]);
      list($pozn,$ucet)=                explode('|',$wh[$i][1]);
      list($datum2,$valuta,$typ)=       explode('|',$wh[$i][2]);
      list($vs,$ks,$ss)=                explode('|',$wh[$i][3]);
      list($castka)=                    explode('|',$wh[$i][4]);
      list($poplatek,$smena,$zprava)=   explode('|',$wh[$i][5]);
      // transformace
      $datum= substr($datum,0,10);
      // výběr informace
      if ( $castka>0 ) {
        // vložení nových informací do tabulky PLATBA - datum,castka,ucet,vs,ks,ss
        $qry= "SELECT * FROM platba
               WHERE ucet='$nazev' AND datum='$datum' AND castka='$castka' AND ucet_nazev='$ucet'
                 AND vs='$vs' AND ks='$ks' AND ss='$ss'";
        $res= mysql_qry($qry);
        if ( !mysql_num_rows($res) ) {
          // vložení nové platby
          $qryu= "INSERT INTO platba (
            ucet,castka,datum,poznamka,zpusob,ucet_nazev,vs,ks,ss) VALUES (
            '$nazev','$castka','$datum','$pozn',1,'$ucet','$vs','$ks','$ss')";
          $resu= mysql_qry($qryu);
          $nove++;
          $lst.= "<br>$castka $pozn $ucet";
        }
      }
    }
    $html.= $nove ? "Vloženo $nove nových plateb z $ucet:<br>$lst" : "Na $cislo nepřišly nové platby";
  }
  if ( !$ok ) {
    $html.= "Při zpracování plateb účtu $cislo došlo k chybě";
  }
  return $html;
}
# ---------------------------------------------------------------- xml funkce
function dom_shownode($x) {
  $txt= '';
  foreach ($x->childNodes as $p)
    if ( dom_hasChild($p) ) {
      $txt.= dom_shownode($p);
    }
    elseif ($p->nodeType == XML_ELEMENT_NODE)
      $txt.= "|";
    elseif ($p->nodeType == XML_TEXT_NODE)
      $txt.= trim($p->nodeValue);
  return $txt;
}
function dom_hasChild($p) {
  if ($p->hasChildNodes()) {
    foreach ($p->childNodes as $c) {
      if ($c->nodeType == XML_ELEMENT_NODE)
        return true;
    }
  }
  return false;
}
# ================================================================================================== GOOGLE
# -------------------------------------------------------------------------------------------------- akce_google_cleni
# přečtení listu "Kroměříž 10" z tabulky "ČlenovéYS_2010-2011"
# načítají se jen řádky ve kterých typ je číslo
function akce_google_cleni() {  trace();
  $n= 0;
  $html= '';
  $cells= google_sheet($ws="Kroměříž 10",$wt="ČlenovéYS_2010-2011",'answer@smidek.eu');
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
//                                                 debug($cells,"přehled členů");
    for ($i= 1; $i<$max_n; $i++) {
      if ( is_numeric($cells['A'][$i]) ) {
        $par= $cells['B'][$i];
        $vps= $cells['C'][$i];
        $castka= $cells['G'][$i];
        list($m,$d,$y)= explode('/',$cells['H'][$i]);
        $dne= "$d.$m.$y";
        $do_dne= "31.12.$y";
        $note= $cells['I'][$i];
//                                                 display("dne=$dne,platba=$platba");
//                                                 break;
        $qry= "SELECT r.nazev,
               GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
               GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
               GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
               GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_z
               FROM osoba AS o
               JOIN tvori AS t USING(id_osoba)
               JOIN rodina AS r USING(id_rodina)
               GROUP BY id_rodina
               HAVING CONCAT(nazev,' ',jmeno_m,' a ',jmeno_z)='$par' ";
        $res= mysql_qry($qry);
        if ( $res ) {
          $n= mysql_num_rows($res);
          if ( $n==0 ) {
            $html.= "<br>{$par} nenalezen";
          }
          elseif ( $n>1 ) {
            $html.= "<br>{$par} je nejednoznačný";
          }
          elseif ( $p= mysql_fetch_object($res) ) {
            $id_m= $p->id_m;
            $id_z= $p->id_z;
            $clen= $vps ? 'c' : 'b';
            $qryo= "SELECT o.id_osoba
               FROM osoba AS o
               JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=1
               WHERE o.id_osoba='$id_m' ";
            $reso= mysql_qry($qryo);
            if ( $reso && !mysql_num_rows($reso) ) {
              // přidej mezi členy
              $qryu= "INSERT dar(id_osoba,ukon,dat_od,note) VALUES
                      ('$id_m','$clen','2011-01-01','$note'),('$id_z','$clen','2011-01-01','$note')";
              $resu= mysql_qry($qryu);
//               $html.= "<br>{$par} přidáni mezi členy";
              if ( $castka ) {
              // vlož platbu členského příspěvku
                $sql_dne= sql_date($dne,1);
                $sql_do_dne= sql_date($do_dne,1);
                $castka2= $castka/2;
                $qryu= "INSERT dar(id_osoba,ukon,castka,dat_od,dat_do,note) VALUES
                        ('$id_m','p',$castka2,'$sql_dne','$sql_do_dne','$note')
                       ,('$id_z','p',$castka2,'$sql_dne','$sql_do_dne','$note')";
                $resu= mysql_qry($qryu);
//                 $html.= "<br>{$par} příspěvek";
              }
//                                                 break;
            }
            else {
//             $html.= "<br>{$par} členy jsou";
//             $html.= "<br>{$par} mají {$p->_pocet} id_dupary={$p->id_dupary} $vps $platba $dne $pozn";
            }
          }
        }
      }
    }
  }
  return "Přečtena tabulka $wt.$ws jako A1:$max_A{$max_n}<br>$html";
}
# -------------------------------------------------------------------------------------------------- akce_roku_id
# definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
function akce_roku_id($id_akce,$kod,$rok) {
  // smazání starých spojek
  $r1= mysql_qry("DELETE FROM join_akce WHERE g_rok=$rok AND g_kod=$kod ");
  // vložení nové
  $r2= mysql_qry("INSERT join_akce (id_akce,g_rok,g_kod) VALUES ('$id_akce',$rok,$kod) ");
  return "$r1,$r2";
}
// # -------------------------------------------------------------------------------------------------- akce_roku_id
// # definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
// function akce_roku_id($kod,$rok,$source,$akce) {
//   if ( $akce ) {
//     mysql_qry("INSERT join_akce (source,akce,g_kod,g_rok) VALUES ('$source',$akce,$kod,$rok)");
//     mysql_qry("UPDATE ms_akce SET ciselnik_akce=$kod,ciselnik_rok=$rok WHERE source='$source' AND akce=$akce");
//   }
//   return 1;
// }
# -------------------------------------------------------------------------------------------------- akce_roku_update
# přečtení listu $rok z tabulky ciselnik_akci a zapsání dat do tabulky
# načítají se jen řádky ve kterých typ='a'
function akce_roku_update($rok) {  trace();
  $n= 0;
  $cells= google_sheet($rok,"ciselnik_akci",'answer@smidek.eu');
                                                debug($cells,"akce $rok");
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
    // zrušení daného roku v GAKCE
    $qry= "DELETE FROM g_akce WHERE g_rok=$rok";
    $res= mysql_qry($qry);
    // výběr a-záznamů a zápis do GAKCE
    $values= ''; $del= '';
    for ($i= 1; $i<$max_n; $i++) {
      $kat= $cells['A'][$i];
      if ( strpos(' au',$kat) ) {
//       if ( strpos(' a',$kat) ) {
        $n++;
        $kod= $cells['B'][$i];
        $id= 1000*rok+$kod;
        $nazev= mysql_real_escape_string($cells['C'][$i]);
        // data akce - jen je-li syntax ok
        $od= $do= '';
        $x= $cells['D'][$i];
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $od= sql_date($x,1);
        $x= $cells['E'][$i];
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $do= sql_date($x,1);
        $uc= $cells['F'][$i];
        $typ= $cells['G'][$i];
        $kap= $cells['H'][$i];
        $values.= "$del($id,$rok,'$kod',\"$nazev\",'$od','$do','$uc','$typ','$kap','$kat')";
        $del= ',';
      }
    }
    $qry= "INSERT INTO g_akce (id_gakce,g_rok,g_kod,g_nazev,g_od,g_do,g_ucast,g_typ,g_kap,g_kat)
           VALUES $values";
    $res= mysql_qry($qry);
  }
  // konec
  return $n;
}
# -------------------------------------------------------------------------------------------------- akce_mapa
# získání seznamu souřadnic bydlišť účastníků akce
function akce_mapa($akce) {  trace();
  // dotaz
  $marks= $del= ''; $n= 0;
  $qry=  "SELECT psc,lat,lng
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          JOIN uir_adr.psc_axy USING(psc)
          WHERE p.id_akce='$akce'";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    $n++;
    $marks.= "$del{$s->lat},{$s->lng}"; $del= ';';
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n);
                                                debug($ret,"mapa_akce");
  return $ret;
}
# ================================================================================================== ÚČASTNÍCI
# -------------------------------------------------------------------------------------------------- akce_strava_denne
# vrácení výjimek z providelné stravy jako pole
function akce_strava_denne($od,$dnu,$cela,$polo) {  #trace('');
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
# -------------------------------------------------------------------------------------------------- akce_strava_denne_save
# zapsání výjimek z providelné stravy - pokud není výjimka zapíše prázdný string
#   $prvni - kód první stravy na akci
function akce_strava_denne_save($id_pobyt,$dnu,$cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str,$prvni) {  #trace('');
  $cela_ruzna= $polo_ruzna= 0;
  $i0= $prvni=='s' ? 0 : ($prvni=='o' ? 1 : ($prvni=='v' ? 2 : 2));
  for ($i= $i0; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( ";$cela"!=";$cela_str" ) $set.= "cstrava_cel='$cela'";           // ; jako ochrana pro pochopení jako čísla
  if ( ";$polo"!=";$polo_str" ) $set.= ($set?',':'')."cstrava_pol='$polo'";
  if ( $set ) {
    $qry= "UPDATE pobyt SET $set WHERE id_pobyt=$id_pobyt";
    $res= mysql_qry($qry);
  }
//                                                 display("akce_strava_denne_save(($id_pobyt,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ================================================================================================== CHLAPI AKCE
# funkce pro kartu CHLAPI
# -------------------------------------------------------------------------------------------------- chlapi_mrop_export
# export iniciovaných chlapů do Excelu
function chlapi_mrop_export() {  #trace();
  global $ezer_path_docs;
  // zahájení exportu
  $ymd= date('Ymd');
  $dnes= date('j. n. Y');
  $t= "Iniciovaní chlapi, stav ke dni $dnes";
  $file= "mrop_$ymd";
  $type= 'xls';
  $par= (object)array('file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "prijmeni:příjmení,jmeno:jméno,ulice,psc:psč,obec,iniciace";
  $titles= $fields= $del= '';
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields.= "$del$field";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles);
  $qry= "SELECT $fields FROM chlapi WHERE iniciace!='' ";
  $res= mysql_qry($qry);
  // projití záznamů
  $values= array();
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    foreach ($row as $f => $val) {
      $a= $val;
      if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
      $values[$f]= $a;
    }
    export_row($values);
  }
   export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $ref= "seznam ve formátu <a href='docs/$file.$type'>Excel</a>";
  return $ref;
}
# -------------------------------------------------------------------------------------------------- chlapi_akce_export
# export účastníků akce do Excelu
function chlapi_akce_export($id_akce,$nazev) {  #trace();
  global $ezer_path_docs;
  // zahájení exportu
  $ymd= date('Ymd');
  $dnes= date('j. n. Y');
  $t= "$nazev, stav ke dni $dnes";
  $file= "akce_{$id_akce}_$ymd";
  $type= 'xls';
  $par= (object)array('file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "prijmeni:příjmení,jmeno:jméno,narozeni:narození,ulice,psc:psč,obec,email,telefon,
           iniciace,c.pozn AS c_pozn:poznámka,u.pozn:... k akci,u.cena:cena,u.avizo:avizo,
           u.uctem:účtem,u.pokladnou:pokladnou";
  $titles= $fields= $del= '';
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields.= "$del$field";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles);
  $qry= "SELECT $fields
         FROM ch_ucast AS u JOIN chlapi AS c USING(id_chlapi) WHERE id_akce=$id_akce ";
  $res= mysql_qry($qry);
  // projití záznamů
  $values= array();
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    foreach ($row as $f => $val) {
      $a= $val;
      if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
      $values[$f]= $a;
    }
    export_row($values);
  }
   export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $ref= "seznam ve formátu <a href='docs/$file.$type'>Excel</a>";
  return $ref;
}
# -------------------------------------------------------------------------------------------------- chlapi_delete
# bezpečné smazání chlapa s kontrolou, zda není zařazen v nějaké akci
function chlapi_delete($id_chlapi) {  #trace();
  $ans= '';
  $qry= "SELECT GROUP_CONCAT(nazev) AS _a, prijmeni, jmeno
         FROM ch_ucast
         JOIN ch_akce USING(id_akce)
         JOIN chlapi USING(id_chlapi)
         WHERE id_chlapi='$id_chlapi' GROUP BY id_chlapi ";
  $res= mysql_qry($qry);
  if ( $res && $a= mysql_fetch_object($res) ) {
    $ans= $a->_a
      ? "Nelze smazat, protože '{$a->prijmeni} {$a->jmeno}' se se zúčastní těchto akcí: {$a->_a}"
      : '';
  }
  if ( $res && !$ans ) {
    // vymaž ho
    global $USER;
    $dnes= date('Y-m-d');
    $zmeny= array((object)array('fld'=>'deleted','op'=>'u','val'=>"D {$USER->abbr} $dnes"));
    $ok= ezer_qry("UPDATE",'chlapi',$id_chlapi,$zmeny,'id_chlapi');
  }
  return $ans;
}
# -------------------------------------------------------------------------------------------------- akce_auto_jmena
# souhrn akce
function chlapi_akce_prehled($id_akce) {  #trace();
  $html= '';
  $tab= '';
  $n= 0;
  // základní údaje
  $qry= "SELECT * FROM ch_akce WHERE id_akce='$id_akce' ";
  $res= mysql_qry($qry);
  $x= mysql_fetch_object($res);
  $od= sql_date1($x->datum_od);
  $do= sql_date1($x->datum_do);
  $html.= "<h3>{$x->nazev}, $od - $do</h3>";
  // účastníci
  $qry= "SELECT zkratka AS _x, count(*) AS _n FROM ch_ucast JOIN chlapi USING(id_chlapi)
         JOIN _cis ON druh='akce_ucast' AND data=stupen WHERE id_akce='$id_akce' GROUP BY stupen";
  $res= mysql_qry($qry);
  while ( $res && $a= mysql_fetch_object($res) ) {
    $n+= $a->_n;
    $tab.= "<tr><td>{$a->_x}</td><td align='right'>{$a->_n}</td></tr>";
  }
  $html.= "<b>Celkem $n účastníků, z toho:</b>";
  $html.= "<br/><br/><table>$tab</table>";
  // cena
  $qry= "SELECT sum(cena) AS c, sum(IF(avizo,cena,0)) AS a, sum(uctem) AS u, sum(pokladnou) AS p
         FROM ch_ucast WHERE id_akce='$id_akce' ";
  $res= mysql_qry($qry);
  $c= mysql_fetch_object($res);
  $html.= "<br/><b>Cena akce pro účastníky: {$x->cena}</b>";
  $html.= "<br/><br/><table>";
  $html.= "<tr><td>celkem předepsaná cena:</td><td align='right'>{$c->c}</td></tr>";
  $html.= "<tr><td>přišlo avízo platby:</td><td align='right'>{$c->a}</td></tr>";
  $html.= "<tr><td>zatím zaplaceno účtem:</td><td align='right'>{$c->u}</td></tr>";
  $html.= "<tr><td>zatím zaplaceno pokladnou:</td><td align='right'>{$c->p}</td></tr>";
  $html.= "</table>";
  return $html;
}
# ================================================================================================== PRIDEJ JMENEM
# funkce pro spolupráci se select
# -------------------------------------------------------------------------------------------------- chlapi_auto_jmena
# kontrola, zda chlap ještě na akci není
function chlapi_auto_not_yet($id_akce,$id_chlapi) {
  $qry= "SELECT count(*) AS _pocet
         FROM ch_ucast
         WHERE id_akce='$id_akce' AND id_chlapi='$id_chlapi' ";
  $res= mysql_qry($qry);
  $t= mysql_fetch_object($res);
  return $t->_pocet ? 0 : 1;
}
# -------------------------------------------------------------------------------------------------- chlapi_auto_jmena
# SELECT autocomplete - výběr z akcí
function chlapi_auto_jmena($patt) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  // rodiče
  $qry= "SELECT id_chlapi AS _key,CONCAT(prijmeni,' ',jmeno) AS _value
         FROM chlapi
         WHERE prijmeni LIKE '$patt%' ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné jméno nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# -------------------------------------------------------------------------------------------------- chlapi_auto_jmenovci
# formátování autocomplete
function chlapi_auto_jmenovci($id_pary) {  #trace();
  $a= array();
  // páry na akci
  $qry= "SELECT * FROM chlapi WHERE id_chlapi=$id_pary ORDER BY prijmeni";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno}, {$p->obec} ({$p->id_chlapi})";
    $a[]= (object)array('id_chlapi'=>$p->id_chlapi,'nazev'=>$nazev);
  }
//                                                                 debug($a,$id_chlapi);
  return $a;
}
# ================================================================================================== PRIDEJ JMENEM
# funkce pro spolupráci se select
# -------------------------------------------------------------------------------------------------- akce_auto_jmena2
# SELECT autocomplete - výběr z párů
function akce_auto_jmena2($patt) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  $is= strpos($patt,' ');
  $patt= $is ? substr($patt,0,$is) : $patt;
  // páry
  $qry= "SELECT nazev, r.obec, id_rodina AS _key,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') AS _zena
         FROM rodina AS r
         JOIN tvori AS t USING(id_rodina)
         JOIN osoba AS o USING(id_osoba)
         WHERE nazev LIKE '$patt%'
         GROUP BY id_rodina HAVING _muz!='' AND _zena!=''
         ORDER BY nazev,_muz,_zena
         LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->nazev} {$t->_muz} a {$t->_zena}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné jméno nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# --------------------------------------- akce_auto_jmena2L
# formátování autocomplete
function akce_auto_jmena2L($id_rodina) {  #trace();
  $pary= array();
  // páry
  $qry= "SELECT nazev,
           IFNULL(r.nazev,o.prijmeni) as _nazev,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id,
           r.obec
         FROM rodina AS r
         LEFT JOIN tvori AS t USING(id_rodina)
         LEFT JOIN osoba AS o USING(id_osoba)
         WHERE id_rodina='$id_rodina'
	 GROUP BY id_rodina ORDER BY nazev";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $nazev.= ", {$p->obec}";
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# -------------------------------------------------------------------------------------------------- akce_auto_jmena1
# SELECT autocomplete - výběr z dospělých jednotlivců
function akce_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // páry
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         JOIN tvori USING(id_osoba)
         WHERE concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' AND role!='d'
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
# --------------------------------------- akce_auto_jmena1L
# formátování autocomplete
function akce_auto_jmena1L($id_osoba) {  #trace();
  $pary= array();
  // páry
  $qry= "SELECT prijmeni, jmeno, id_osoba, r.obec,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id
         FROM osoba AS o
         LEFT JOIN tvori AS t USING(id_osoba)
         LEFT JOIN rodina AS r USING(id_rodina)
         WHERE id_osoba='$id_osoba'
         GROUP BY id_rodina";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno}";
    $nazev.= ", {$p->obec}";
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# -------------------------------------------------------------------------------------------------- akce_auto_jmena3
# SELECT autocomplete - výběr z pečounů
function akce_auto_jmena3($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // páry
  $qry= "SELECT prijmeni, jmeno, osoba.id_osoba AS _key
         FROM osoba
         JOIN spolu USING(id_osoba)
         JOIN pobyt USING(id_pobyt)
         WHERE concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' AND funkce=99
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
# --------------------------------------- akce_auto_jmena3L
# formátování autocomplete
function akce_auto_jmena3L($id_osoba) {  #trace();
  $pecouni= array();
  // páry
  $qry= "SELECT id_osoba, prijmeni, jmeno, obec
         FROM osoba AS o
         WHERE id_osoba='$id_osoba' ";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno}, {$p->obec}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# ================================================================================================== PRIDEJ z AKCE
# -------------------------------------------------------------------------------------------------- akce_auto_akce
# SELECT autocomplete - výběr z akcí
function akce_auto_akce($patt) {  #trace();
  $a= array();
  $limit= 20;
  $patt= substr($patt,-7,2)==' (' && substr($patt,-1)==')' ? substr($patt,0,-7) : $patt;
  $n= 0;
  // výběr akce
  $qry= "SELECT id_duakce AS _key,concat(nazev,' (',YEAR(datum_od),')') AS _value
         FROM akce
         WHERE nazev LIKE '$patt%'
         ORDER BY datum_od DESC LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
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
# ---------------------------------------- akce_auto_akceL
# formátování autocomplete
function akce_auto_akceL($id_akce) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT
           IFNULL(r.nazev,o.prijmeni) as _nazev,
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
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# -------------------------------------------------------------------------------------------------- akce_auto_pece
# SELECT autocomplete - výběr z akcí na kterých byli pečouni
function akce_auto_pece($patt) {  #trace();
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
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
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
# ---------------------------------------- akce_pece_akceL
# formátování výběru pečounů dané akce
function akce_auto_peceL($id_akce) {  #trace();
  $pecouni= array();
  // páry na akci
  $qry= "SELECT o.id_osoba,jmeno,prijmeni,obec
         FROM pobyt AS p
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         WHERE id_akce=$id_akce AND p.funkce=99
	 ORDER BY prijmeni,jmeno";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno}, {$p->obec}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# ================================================================================================== INFORMACE
# výpisy informací o akci
# -------------------------------------------------------------------------------------------------- akce_info
# základní informace a obsazenost
function akce_info($id_akce) {  trace();
  $html= '';
  if ( $id_akce ) {
      $dosp= $deti= 0;
      $akce= '';
      $qry= "SELECT nazev, COUNT(DISTINCT p.id_pobyt) AS _rodin, datum_od, datum_do, now() as _ted,
               SUM(IF(t.role='a',1,0)) AS _muzu,
               SUM(IF(t.role='b',1,0)) AS _zen,
               SUM(IF(t.role='d',1,0)) AS _deti
             FROM akce AS a
             JOIN pobyt AS p ON a.id_duakce=p.id_akce
             JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
             JOIN osoba AS o ON s.id_osoba=o.id_osoba
             LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
             WHERE id_duakce='$id_akce'
             GROUP BY id_duakce ";
      $res= mysql_qry($qry);
      if ( $res && $p= mysql_fetch_object($res) ) {
        $dosp= $p->_muzu + $p->_zen;
        $deti= $p->_deti;
        $rod= $p->_rodin;
        $akce= $p->nazev;
        $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
        $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
        $od= sql_date1($p->datum_od);
        $do= sql_date1($p->datum_do);
        $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
      }
      $html= $rod>0
       ? "Akce <b>$akce</b><br>$cas1 $dne<br><br>$cas2
         <br>$dosp dospělých a<br>$deti dětí, tvořících<br>$rod rodin"
       : "Akce byla vložena do databáze<br>ale nemá zatím žádné účastníky";
  }
  else {
    $html= "Tato akce ještě nebyla
            <br>vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            <br>na řádek s akcí";
  }
  return $html;
}
# ================================================================================================== EVIDENCE
# obsluha karet EVIDENCE
# -------------------------------------------------------------------------------------------------- evid_separ_emaily
# separace emailů
#   $typ = M | MZ | MMZ | Z
#   $op  = confirm | change
function evid_separ_emaily($id_rodina,$typ,$op='confirm') { trace();
  $html= 'ok';
  $r= sql_query(
    "SELECT emaily,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.email,'')    SEPARATOR '') AS _muz_email,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.email,'')    SEPARATOR '') AS _zena_email
     FROM rodina AS r
     LEFT JOIN tvori AS t USING(id_rodina)
     LEFT JOIN osoba AS o USING(id_osoba)
     WHERE id_rodina=$id_rodina
     GROUP BY id_rodina ");
  $emaily= trim($r->emaily,"\n\r\t ,;");
  $muz_email= trim($r->_muz_email,"\n\r\t ,;");
  $zena_email= trim($r->_zena_email,"\n\r\t ,;");
  $m= preg_split("/\s*,\s*|\s*;\s*/",$emaily);
  switch($typ) {
  case 'M':   $muz= $emaily; $zena= ''; break;
  case 'MZ':  $muz= $m[0]; $zena= $m[1]; break;
  case 'MMZ': $muz= "{$m[0]},{$m[1]}"; $zena= $m[2]; break;
  case 'Z':   $muz= ''; $zena= $emaily; break;
  }
  if ( $op=='confirm' ) {
    $me= $muz_email ? "\nmá: $muz_email" : '';
    $ze= $zena_email ? "\nmá: $zena_email" : '';
    $html= "Mám rozdělit maily takto?\n\n{$r->_muz}:\n$muz$me\n\n{$r->_zena}:\n$zena$ze\n";
  }
  elseif ( $op=='change' ) {
    if ( $r->emaily != '' )
      ezer_qry("UPDATE",'rodina',$id_rodina,array((object)array(
        'fld'=>'emaily','op'=>'u','val'=>'','old'=>$r->emaily)));
    if ( $r->_muz_email != $muz )
      ezer_qry("UPDATE",'osoba',$r->_muz_id,array((object)array(
        'fld'=>'email','op'=>'u','val'=>$muz,'old'=>$r->_muz_email)));
    if ( $r->_zena_email != $zena )
      ezer_qry("UPDATE",'osoba',$r->_zena_id,array((object)array(
        'fld'=>'email','op'=>'u','val'=>$zena,'old'=>$r->_zena_email)));
  }
  return $html;
}
# ================================================================================================== VYPISY EVIDENCE
# obsluha různých forem výpisů karty EVIDENCE
# -------------------------------------------------------------------------------------------------- evid_sestava
# generování sestav
#   $typ = e-j | e-vps
function evid_sestava($par,$title,$export=false) {
  return $par->typ=='e-j' ? evid_sestava_j($par,$title,$export)
     : ( $par->typ=='e-v' ? evid_sestava_v($par,$title,$export)
     : ( $par->typ=='e-s' ? evid_sestava_s($par,$title,$export)
                          : fce_error("evid_sestava: N.Y.I.") ));
}
# -------------------------------------------------------------------------------------------------- evid_vyp_excel
# generování tabulky do excelu
function evid_vyp_excel($par,$title) {  trace();
  $tab= evid_sestava($par,$title,true);
  $subtitle= "ke dni ".date("d. m. Y");
//                                                         debug($tab,"evid_vyp_excel");
  return akce_vyp_excel("",$tab->par,$title,$subtitle,$tab);
}
# -------------------------------------------------------------------------------------------------- evid_table
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function evid_table($par,$tits,$flds,$clmn,$export=false) {
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
        $align= is_numeric($c[$f]) ? "right" : "left";
        $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>
      $n řádků<br><br>{$par->txt}</div>";
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- evid_sestava_s
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
function evid_sestava_s($par,$title,$export=false) {
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= evid_sestava_v($par,$title,true);
//                                                         debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,párů,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,dětí na kurzu";
  $tits= explode(',',$tit);
  $fld= "rr,x,n,p,v,vo,vs,d";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=1990;$rrrr<=date('Y');$rrrr++) {
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rr,'x'=>0);
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
         WHERE a.druh=1 AND p.funkce IN (0,1)
         GROUP BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $rr= substr($x->_rok,-2);
    $clmn[$rr]['d']+= $x->_deti;
    $clmn[$rr]['x']+= $x->_pary;
  }
                                        debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "x:n,p,v,vo,vs,d";
  $par->txt= "Pozn. Graficky je znázorněn relativní počet vzhledem k počtu párů.;
    <br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_v
# generování přehledu akčnosti VPS
function evid_sestava_v($par,$title,$export=false) {
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
        WHERE a.druh=1 AND p.funkce IN (0,1)  /* AND LEFT(r.nazev,3)='Šmí' */
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
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_j
# generování dat sestavy - zatím jen jednotlivci  $par->typ = j
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function evid_sestava_j($par,$title,$export=false) {
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $qry= "SELECT
           os.prijmeni,os.jmeno,os.narozeni,os.sex,
           os.obec,os.ulice,os.psc,os.email,r.emaily,
           GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
           GROUP_CONCAT(CONCAT(ukon,':',dat_od,':',castka) ORDER BY dat_od DESC SEPARATOR '|') AS _dar
         FROM osoba AS os
         JOIN tvori AS ot ON os.id_osoba=ot.id_osoba
         JOIN rodina AS r USING(id_rodina)
         LEFT JOIN dar AS od ON os.id_osoba=od.id_osoba AND od.deleted=''
         WHERE os.deleted='' AND {$par->cnd}
         GROUP BY os.id_osoba HAVING {$par->hav}
         ORDER BY os.id_osoba";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_kc':                               // kolektivní člen
        $clmn[$n][$f]= 'YMCA Setkání';
        break;
      case '_email':                            // osobní mail nebo první rodinný
        $e= $x->email;
        if ( !$e ) {
          list($em,$ez)= preg_split('/[,;]/',$x->emaily);
          $e= trim($x->sex==1 ? $em : $ez);
        }
        $clmn[$n][$f]= $e;
        break;
      case '_rok': break;                       // rokd posledního členského příspěvku ... viz níže
      case '_prisp':                            // poslední členský příspěvek
        $p= $r= '';
        if ( $x->_dar ) {
          foreach(explode('|',$x->_dar) as $udc) {
            list($u,$d,$c)= explode(':',$udc);
            if ( $u=='p' ) {
              $p= $c;
              $r= substr($d,0,4);
              break;
            }
          }
        }
        $clmn[$n][$f]= $p;
        if ( in_array('_rok',$flds) ) $clmn[$n]['_rok']= $r;
        break;
      case '_clen':                             // druh členství
        $clmn[$n][$f]= strpos($x->rel,'c')!==false ? 'č' : (
                       strpos($x->rel,'b')!==false ? 'b' : (
                       strpos($x->rel,'k')!==false ? 'k' : '-'));
        break;
      case '_naroz':                            // narozeni yymmdd
        $clmn[$n][$f]= substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2);
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
  }
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# ================================================================================================== VYPISY AKCE
# obsluha různých forem výpisů karet AKCE
# -------------------------------------------------------------------------------------------------- akce_vyp_excel
# generování tabulky do excelu
function akce_vyp_excel($akce,$par,$title,$vypis,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= akce_sestava($akce,$par,$title,$vypis,true);
//                                         debug($tab,"akce_sestava($akce,...)"); return;
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
  foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    $lc++;
  }
  $lc= 0;
  foreach ($tab->tits as $idw) {
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
  foreach ($tab->clmn as $i=>$c) {
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
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$val);
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
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//                                                                 display($xls);
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
# ================================================================================================== EMAILY - SQL
# vytváření a testování SQL dotazů pro definici mailů
# -------------------------------------------------------------------------------------------------- db_mail_copy_ds
# ASK - kopie tabulky SETKANI.DS_OSOBA do EZER_YS.DS_OSOBA_COPY
# vrací {id_cis,data,query}
function db_mail_copy_ds() {  trace();
  global $ezer_db;
  $html= 'kopie se nepovedla';
  // smazání staré kopie
  $qry= "TRUNCATE TABLE ezer_ys.ds_osoba_copy ";
  $ok= mysql_qry($qry);
  if ( $ok ) {
    $html= "inicializace ds_osoba_copy ok";
    ezer_connect('setkani');
    $qrs= "SELECT * FROM setkani.ds_osoba WHERE email!='' ";
    $res= mysql_qry($qrs);
    while ( $res && ($s= mysql_fetch_object($res)) ) {
//                                                         debug($s,'s',(object)array('win1250'=>1));
      $ids= $vals= $del= '';
      foreach($s as $id=>$val) {
        $ids.= "$del$id";
        $vals.= "$del'".mysql_real_escape_string(wu($val))."'";
        $del= ',';
      }
      $qry= "INSERT INTO ezer_ys.ds_osoba_copy ($ids) VALUES ($vals)";
      $ok= mysql_query($qry,$ezer_db['ezer_ys'][0]);
                                                        display("$ok:$qry");
      if ( !$ok ) {
        $html.= "\nPROBLEM ".mysql_error();
      }
    }
    if ( $ok ) {
      $html.= "\nkopie do ds_osoba_copy ok";
    }
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- db_mail_sql_new
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function db_mail_sql_new() {  #trace();
  $id= select("MAX(0+id_cis)","_cis","druh='db_maily_sql'");
  $data= select("MAX(0+data)","_cis","druh='db_maily_sql'");
  $result= (object)array(
    'id'=>$id+1, 'data'=>$data+1,
    'qry'=>"SELECT id_... AS _id,prijmeni,jmeno,ulice,psc,obec,email,telefon FROM ...");
  return $result;
}
# -------------------------------------------------------------------------------------------------- db_mail_sql_try
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function db_mail_sql_try($qry,$vsechno=0) {  trace();
  $html= $head= $tail= '';
  $emails= array();
  try {
    $time_start= getmicrotime();
    $res= mysql_qry($qry);
    $time= round(getmicrotime() - $time_start,4);
    if ( !$res ) {
      $html.= "<span style='color:darkred'>ERROR ".mysql_error()."</span>";
    }
    else {
      $nmax= $vsechno ? 99999 : 200;
      $num= mysql_num_rows($res);
      $head.= "Výběr obsahuje <b>$num</b> emailových adresátů, nalezených během $time ms, ";
      $head.= $num>$nmax ? "následuje prvních $nmax adresátů" : "následují všichni adresáti";
      $tail.= "<br><br><table class='stat'>";
      $tail.= "<tr><th>prijmeni jmeno</th><th>email</th><th>telefon</th>
        <th>ulice psc obec</th><th>x</th></tr>";
      $n= $nmax;
      while ( $res && ($c= mysql_fetch_object($res)) ) {
        if ( $n ) {
          $tail.= "<tr><td>{$c->prijmeni} {$c->jmeno}</td><td>{$c->_email}</td><td>{$c->telefon}</td>
            <td>{$c->ulice} {$c->psc} {$c->obec}</td><td>{$c->_x}</td></tr>";
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
  }
  catch (Exception $e) { $html.= "<span style='color:red'>FATAL ".mysql_error()."</span>";  }
  $head.= "<br>Adresáti mají <b>".count($emails)."</b> různých emailových adres";
  $html= $html ? $html : $head.$tail;
                                                debug($emails,"db_mail_sql_try");
  return $html;
}
# ================================================================================================== EMAILY
# podpora přihlášek do Klubu
# -------------------------------------------------------------------------------------------------- db_mail_confirm_yes
# ASK
# přijetí potvrzení kliknutím na $url&conf=$id_webform&veri=md5
function db_mail_confirm_yes($id_webform,$md5) {  trace();
  // vyzvednutí údajů z přihlášky
  $potvrzeno= '?';
  $qry= "SELECT * FROM webform WHERE id_webform='$id_webform' ";
  $res= mysql_qry($qry);
  if ( $res && ($w= mysql_fetch_object($res)) ) {
    // kontrola vyplněných položek
    $kod= md5($id_webform.$w->jmeno.$w->prijmeni.$w->email);
    $potvrzeno= $w->potvrzeno;
    $potvrzeno.= date("d.m.Y H:i ").($kod==$md5 ? "ok" : "?");
    $qryu= "UPDATE webform SET potvrzeno='$potvrzeno|' WHERE id_webform='$id_webform' ";
    $resr= mysql_qry($qryu);
  }
  return "$potvrzeno ($id_webform,$md5)";
}
# -------------------------------------------------------------------------------------------------- db_mail_confirm_ask
# ASK
# zaslání emailu s žádostí o potvrzení kliknutím na $url&conf=$id_webform&veri=md5
function db_mail_confirm_ask($id_webform,$url) {  trace();
  $from= "cerny.vavrovice@seznam.cz";
  $from= "martin@smidek.eu";
  // vyzvednutí údajů z přihlášky
  $qry= "SELECT * FROM webform WHERE id_webform='$id_webform' ";
  $res= mysql_qry($qry);
  if ( $res && ($w= mysql_fetch_object($res)) ) {
    // rekapitulace vyplněných položek
    $flds= "Jméno: {$w->title} {$w->jmeno} {$w->prijmeni}";
    if ( $w->ulice || $w->psc || $w->obec )
      $flds.="<br>Bydliště: {$w->ulice} {$w->psc} {$w->obec}";
    if ( $w->email || $w->telefon )
      $flds.="<br>Kontakt: {$w->email}; {$w->telefon}";
    // vytvoření potvrzující adresy
    global $path_url;
    $kod= md5($id_webform.$w->jmeno.$w->prijmeni.$w->email);
    $url= "$url&conf=$id_webform&veri=$kod";
    // kompozice těla mailu
    $text= <<<__EOD
    <html><body>
      <p>Děkujeme Vám za vyplnění přihlášky do <i>Klubu přátel YMCA Setkání</i></p>
      Potvrďte prosím správnost uvedených údajů
      <blockquote>$flds</blockquote>
      <br>kliknutím na tento odkaz: $url
      <br>Tím bude vaše přihláška po formální stránce ukončena.
      <p>Těšíme se na naši další spolupráci</p>
    </body></html>
__EOD;
    $obj= db_mail_send($from,$w->email,"Přihláška do Klubu přátel YMCA Setkání",$text);
    $html= $obj->_html;
  }
  else {
    $html= "Nebyly nalezeny údaje ...";
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- db_mail_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
function db_mail_send($from,$to,$subj,$text) { trace();
  global $ezer_path_serv;
  require_once("$ezer_path_serv/licensed/phpmailer/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  $html= '';
  // napojení na mailer
  $mail= new PHPMailer;
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->AddReplyTo($from);
  $mail->FromName= "YMCA Setkání";
  $mail->AddAddress($to);
  $mail->Subject= $subj;
  $mail->Body= $text;
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
  if ( $ok  )
    $html.= "<br><b style='color:#070'>Byl odeslán mail pro $to - je zapotřebí zkontrolovat obsah</b>";
  else {
    $html.= "<br><b style='color:#700'>Při odesílání mailu došlo k chybě: {$mail->ErrorInfo}</b>";
    $result->_error= 1;
  }
  // zpráva o výsledku
  $result->_html= $html;
  return $result;
}
# ================================================================================================== EMAILY
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce dop_mai_qry
# -------------------------------------------------------------------------------------------------- dop_mai_text
# přečtení mailu
function dop_mai_text($id_dopis) {  trace();
  $d= null;
  try {
    $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_mai_text: průběžný dopis No.'$id_dopis' nebyl nalezen"); }
  $html.= "<b>{$d->nazev}</b><br/><hr/>{$d->obsah}";
  // příloha?
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $priloha ) {
      $priloha= $priloha;
      $html.= "<hr/><b>Příloha:</b> $priloha";
      $typ= strtolower(substr($priloha,-4));
      if ( $typ=='.jpg' || $typ=='.gif' || $typ=='.png' ) {
        $html.= "<img src='docs/$priloha' />";
      }
    }
  }
//                                                         debug($d,"dop_mai_text($id_dopis)");
  return $html;
}
# -------------------------------------------------------------------------------------------------- dop_mai_prazdny
# zjistí zda neexistuje starý seznam adresátů
function dop_mai_prazdny($id_dopis) {  trace();
  $result= array('_error'=>0, '_prazdny'=> 1);
  // ověř prázdnost MAIL
  $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis";
  $res= mysql_qry($qry);
  if ( mysql_num_rows($res)>0 ) {
    $result['_html']= "Rozesílací seznam pro tento mail již existuje, stiskni OK pokud má být přepsán novým";
    $result['_prazdny']= 0;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_qry
# sestaví SQL dotaz podle položky DOPIS.komu
# formát zápisu dotazu v číselníku:  A[|D[|cond]]
#   kde A je seznam aktivit oddělený čárkami
#   a D=1 pokud mají být začleněni pouze letošní a loňští dárci
#   a cond je obecná podmínka na položky tabulky CLEN
function dop_mai_qry($komu) {  trace();
  list($aktivity,$is_dary,$cond)= explode('|',$komu);
  $and= $aktivity=='*' ? '' : "AND FIND_IN_SET(aktivita,'$aktivity')";
  if ( $cond ) $and.= " AND $cond";
  $letos= date('Y'); $loni= $letos-1;
  $qry= $is_dary
    ? "SELECT id_clen, email,
         BIT_OR(IF((YEAR(datum) BETWEEN $loni AND $letos) AND LEFT(dar.deleted,1)!='D'
           AND castka>0 AND akce='G',1,0)) AS is_darce
       FROM clen LEFT JOIN dar USING (id_clen)
       WHERE LEFT(clen.deleted,1)!='D' AND umrti='0000-00-00' AND aktivita!=9 AND email!='' $and
       GROUP BY id_clen HAVING is_darce=1"
    : "SELECT id_clen, email FROM clen
       WHERE left(deleted,1)!='D' AND umrti='0000-00-00' AND email!='' $and";
  return $qry;
}
# -------------------------------------------------------------------------------------------------- dop_mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# $dopis_var určuje zdroj adres
#   'U' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze účastnící s funkcí < 3 (-,VPS,SVPS)
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí dop_mai_skupina - DEPRECATED
# $cond = dodatečná podmínka POUZE pro volání z dop_mai_stav
function dop_mai_pocet($id_dopis,$dopis_var,$cond='',$recall=false) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni ZRUŠIT';
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= '';
  $n= $ns= $nt= $nx= $mx= 0;
  $dels= $deln= $delm= '';
  $nazev= '';
  switch ($dopis_var) {
  // obecný dotaz
  case 'Q':
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      $qry= $q->hodnota;
      $res= mysql_qry($qry);
      while ( $res && ($d= mysql_fetch_object($res)) ) {
        $n++;
        $nazev= "Členů {$q->zkratka}";
        if ( $d->_email ) {
          // přidej každý mail zvlášť do seznamu
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            // pokud tam ještě není
            if ( $adr && !in_array($adr,$emaily) ) {
              if ( $adr[0]=='*' ) {
                // vyřazený mail
                $mimo.= "$delm{$d->prijmeni} {$d->jmeno}"; $delm= ', '; $mx++;
              }
              else {
                $emaily[]= $adr;
                $ids[]= $d->_id;
                $jmena[]= "{$d->prijmeni} {$d->jmeno}";
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
  // účastníci akce
  case 'U':
    $AND= $cond ? "AND $cond" : '';
    // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
    // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech
    $qry= "SELECT a.nazev,id_pobyt,
           GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
           GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno) ORDER BY t.role) AS _jm,
           GROUP_CONCAT(DISTINCT o.email) AS email, GROUP_CONCAT(DISTINCT r.emaily) AS emaily
           FROM dopis AS d
           JOIN akce AS a ON d.id_duakce=a.id_duakce
           JOIN pobyt AS p ON d.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           JOIN tvori AS t ON t.id_osoba=o.id_osoba
           JOIN rodina AS r USING (id_rodina)
           WHERE id_dopis=$id_dopis AND p.funkce<3 $AND GROUP BY id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && ($d= mysql_fetch_object($res)) ) {
      $n++;
      $nazev= "Účastníků {$d->nazev}";
      if ( $d->email!='' || $d->emaily!='' ) {
        $em= "{$d->email},{$d->emaily}";
        if ( strpos($em,'*')!==false ) {
          // vyřazený mail
          $mimo.= "$delm$jm"; $delm= ', '; $mx++;
        }
        $emaily[]= $em;
        $pobyty[]= $d->id_pobyt;
        list($ids[])= explode(',',$d->_id);
        list($jmena[])= explode(',',$d->_jm);
      }
      else {
        list($jm)= explode(',',$d->_jm);
        $nema.= "$deln$jm"; $deln= ', ';
        $nx++;
      }
    }
    break;
  }
  // projdi adresy
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
  $html.= $nx ? "$nx nemají mail ($nema)" : '';
  $html.= $mx ? "$mx mají mail označený '*' jako nedostupný ($mimo)" : '';
  $result->_html= $nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni ZRUŠIT";
  $result->_html.= "\n\n$html";
  $result->_count= $nt;
  if ( !$recall ) {
    // pro delší seznamy
    $result->_dopis_var= $dopis_var;
    $result->_cond= $cond ? $cond : '';
    $result->_adresy= array();
    $result->_ids= array();
  }
//                                                 debug($result,"dop_mai_pocet.result");
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po dop_mai_pocet)
# $id_dopis => dopis(&pocet)
# $info = {_adresy,_ids[,_cond]}   _cond
function dop_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
//                                                         debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
//                                                         fce_log("dop_mai_posli: $qry");
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");

  if ( isset($info->_dopis_var) ) {
    // přepočítej adresy
    $info= dop_mai_pocet($id_dopis,$info->_dopis_var,$info->_cond,true);
//     $info->_adresy= $result->_adresy;
  }
  if ( isset($info->_adresy) ) {
    // zjisti text dopisu a jestli obsahuje proměnné
    $obsah= select('obsah','dopis',"id_dopis=$id_dopis");
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
                                                                debug($vars);
    // pokud jsou přímo známy adresy, pošli na ně
    $ids= array();
    foreach($info->_ids as $i=>$id) $ids[$i]= $id;
    if ( $info->_pobyty ) foreach($info->_pobyty as $i=>$pobyt) $pobyty[$i]= $pobyt;
    foreach ($info->_adresy as $i=>$email) {
      $id= $ids[$i];
      // vlož do MAIL - pokud nezačíná *
      if ( $email[0]!='*' ) {
        $id_pobyt= isset($pobyty[$i]) ? $pobyty[$i] : 0;
        // pokud dopis obsahuje proměnné, personifikuj obsah
        $body= $is_vars ? dop_mail_personify($obsah,$vars,$id_pobyt) : '';
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body)
              VALUE (1,'@',0,$id_dopis,$id,$id_pobyt,'$email','$body')";
//                                         display("$i:$qr");
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= dop_mai_qry($info->_cond);
    $res= mysql_qry($qry);
    while ( $res && $c= mysql_fetch_object($res) ) {
      // vlož do MAIL
      if ( $c->email[0]!='*' ) {
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
              VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
//                                                         fce_log("dop_mai_posli: UPDATE");
  $rs= mysql_qry($qr);
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mail_personify
# spočítá proměnné podle id_pobyt a dosadí do textu dopisu
# vrátí celý text
function dop_mail_personify($obsah,$vars,$id_pobyt) {
  $text= $obsah;
  foreach($vars as $var) {
    $val= '';
    switch ($var) {
    case 'akce_cena':
      $ret= akce_vzorec($id_pobyt);
      $val= $ret->mail;
      break;
    }
    $text= str_replace('{'.$var.'}',$val,$text);
  }
  $text= mysql_real_escape_string($text);
  return $text;
}
# -------------------------------------------------------------------------------------------------- dop_mai_info
# informace o členovi
# $id - klíč osoby nebo chlapa
# $zdroj určuje zdroj adres
#   'U' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#   'C' - rozeslat účastníkům akce dopis.id_duakce ukazující do ch_ucast
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
function dop_mai_info($id,$email,$id_dopis,$zdroj) {  trace();
  $html= '';
  switch ($zdroj) {
  case 'C':                     // chlapi
    $qry= "SELECT * FROM chlapi WHERE id_chlapi=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->prijmeni} {$c->jmeno}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefon )
        $html.= "Telefon: {$c->telefon}<br>";
    }
    break;
  case 'Q':                     // číselník
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      // SELECT vrací (_id,prijmeni,jmeno,ulice,psc,obec,email,telefon)
      $qry= $q->hodnota;
      if ( strpos($qry,"GROUP BY") ) {
        if ( strpos($qry,"HAVING") )
          $qry= str_replace("HAVING","HAVING _id=$id AND ",$qry);
        else
          $qry= str_replace("GROUP BY","GROUP BY _id HAVING _id=$id AND ",$qry);
      }
      else {
        $qry.= " GROUP BY _id HAVING _id=$id ";
      }
      $res= mysql_qry($qry);
      while ( $res && ($c= mysql_fetch_object($res)) ) {
        $html.= "{$c->prijmeni} {$c->jmeno}<br>";
        $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
        if ( $c->telefon )
          $html.= "Telefon: {$c->telefon}<br>";
      }
    }
    break;
  case 'U':                     // účastníci akce
    $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
    }
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- dop_mai_smaz
# smazání mailu v DOPIS a jeho rozesílání v MAIL
function dop_mai_smaz($id_dopis) {  trace();
  $qry= "DELETE FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání mailu No.'$id_dopis' se nepovedlo");
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_stav
# úprava stavu mailové adresy
# ZATIM BEZ: (maže maily - nutné zohlednit i id_clen==id_osoba aj.) včetně znovuzískání mailové adresy s karty účastníka
function dop_mai_stav($id_mail,$stav) {  trace();

  list($id_dopis,$id_pobyt)= select("id_dopis,id_pobyt","mail","id_mail=$id_mail");
  $novy_mail= '';
//   if ( $id_pobyt) {
//     $oprava= dop_mai_pocet($id_dopis,'U',$cond="id_pobyt=$id_pobyt");
//     $emaily= $oprava->_adresy[0];
//     $novy_mail= ",email='$emaily'";
//                                                   debug($oprava,"dop_mai_stav:$emaily.");
//   }
  $qry= "UPDATE mail SET stav=$stav$novy_mail WHERE id_mail=$id_mail ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
# $from,$fromname = From,ReplyTo
# $test = 1 mail na tuto adresu (pokud je $kolik=0)
# pokud je definováno $id_mail s definovaným text MAIL.body, použije se - jinak DOPIS.obsah
function dop_mai_send($id_dopis,$kolik,$from,$fromname,$test='',$id_mail=0) { trace();
  global $ezer_path_serv;
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  $pro= '';
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry,1,null,1);
  $d= mysql_fetch_object($res);
  // napojení na mailer
  $html= '';
//   $klub= "klub@proglas.cz";
  $martin= "martin@smidek.eu";
//   $jarda= "cerny.vavrovice@seznam.cz";
//   $jarda= $martin;
  // poslání mailů
  $mail= new PHPMailer;
  $mail->SetLanguage('cz',"$phpmailer_path/language/");
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->AddReplyTo($from);
//   $mail->ConfirmReadingTo= $jarda;
  $mail->FromName= "$fromname";
  $mail->Subject= $d->nazev;
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $fname ) {
      $fpath= "docs/".trim($fname);
      $mail->AddAttachment($fpath);
    }
  }
  if ( $kolik==0 ) {
    // testovací poslání sobě
    if ( $id_mail ) {
      // přečtení personifikace rozesílaného mailu
      $qry= "SELECT * FROM mail WHERE id_mail=$id_mail ";
      $res= mysql_qry($qry,1,null,1);
      $m= mysql_fetch_object($res);
      if ( $m->body )
        $obsah= $m->body;
        $pro= "s personifikací pro {$m->email}";
    }
    $mail->Body= $obsah;
    $mail->AddAddress($test);   // pošli sám sobě
    // pošli
    $ok= $mail->Send();
    if ( $ok  )
      $html.= "<br><b style='color:#070'>Byl odeslán mail na $test $pro - je zapotřebí zkontrolovat obsah</b>";
    else {
      $err= $mail->ErrorInfo;
      $html.= "<br><b style='color:#700'>Při odesílání mailu došlo k chybě: $err</b>";
      $result->_error= 1;
    }
//                                                 display($html);
  }
  else {
    // poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav IN (0,3)";
    $res= mysql_qry($qry);
    while ( $res && ($z= mysql_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $n++;
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
      $mail->Body= $obsah;
      foreach(explode(',',$z->email) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
//       $mail->AddBCC($klub);
      // zkus poslat mail
      try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
      if ( !$ok  ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $err= $mail->ErrorInfo;
        $html.= "<br><b style='color:#700'Při odesílání mailu pro $ident došlo k chybě: $err</b>";
        $result->_error= 1;
        $nko++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok ? 4 : 5;
      $msg= $ok ? '' : $mail->ErrorInfo;
      $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
      $res1= mysql_qry($qry1);
    }
    $html.= "<br><b style='color:#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
//                                                 debug($result,"dop_mai_send");
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_attach
# přidá přílohu k mailu jako odkaz, soubor bude v docs
function dop_mai_attach($id_dopis,$fileinfo) { trace();
  global $ezer_path_root;
  $f= pathinfo($fileinfo->name);
  $name= utf2ascii($f['filename']).'.'.utf2ascii($f['extension']);
  $path= "$ezer_path_root/docs/$name";
  // uložení souboru
  $data= base64_decode($fileinfo->text);
  $bytes= file_put_contents($path,$data);
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $names= $names ? "$names,$name" : $name;
  query("UPDATE dopis SET prilohy='$names' WHERE id_dopis=$id_dopis");
  $refs= dop_mai_refs($names);
  return $refs;
}
# -------------------------------------------------------------------------------------------------- dop_mai_detach
# odstraní všechny odkazy na přílohy mailu
function dop_mai_detach($id_dopis) { trace();
  query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
  return 1;
}
# -------------------------------------------------------------------------------------------------- dop_mai_refs
# převede seznam jmen na odkazy
function dop_mai_refs($names) {
  $refs= array();
  foreach(explode(',',$names) as $name) {
    $ref= "<a href='docs/$name' title='$name' target='priloha'>$name</a>";
    $refs[]= $ref;
  }
  return implode(' &nbsp; ',$refs);
}
# -------------------------------------------------------------------------------------------------- dop_mai_skupina
# ASK
# připrav mailové adresy dané skupiny
function dop_mai_skupina($skupina) { trace();
  global $dop_mai_v2010;
  $result= (object)array('_error'=>0);
  $adresy= array();
  // výběr mailů do pole $adresy a naplnění $html
  switch ($skupina) {
  # výbor
  case 'martin':
  case 'vybor':
    $t= $dop_mai_v2010[$skupina];
    $n= $nt= $nx= 0;
    foreach($t as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        $adresy[]= $adr;
        $nt++;
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$nt ve skupině: $skupina</h3>".implode('<br>',$adresy);
    $html.= "<h3>$nx bylo vyřazeno</h3>";
    break;
  # vánoce 2010
  case 'vanoce2010':
    $j= $dop_mai_v2010['jarda'];
    $k= $dop_mai_v2010['konf'];
    $n= $nj= $nk= $nx= 0;
    $in_jk= $not_in_k= $not_in_j= array();
    foreach($k as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        if ( in_array($adr,$j) ) {
          $in_jk[]= $adr;
          $n++;
        }
        else {
          $not_in_j[]= $adr;
          $nj++;
        }
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$n je v JARDA i KONF</h3>".implode('<br>',$in_jk);
    $html.= "<h3>$nj není v JARDA</h3>".implode('<br>',$not_in_j);
    $nk= 0;
    foreach($j as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        if ( !in_array($adr,$k) ) {
          $not_in_k[]= $ok.$adr;
          $nk++;
        }
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$nk není v KONF</h3>".implode('<br>',$not_in_k);
    $html.= "<h3>$nx bylo vyřazeno</h3>";
    $adresy= array_merge($in_jk,$not_in_j,$not_in_k);
  }
  // zápis pole $adresa
  $adresy= array_unique($adresy);
  sort($adresy);
  $html= "<h3>".count($adresy)." adres bude použito jako '$skupina'</h3>".implode('<br>',$adresy);
  $result->_html= $html;
  $result->adresy= $adresy;
  return $result;
}
$dop_mai_v2010= array(
'martin'=> array(
//   "michalec.zdenek@inset.com",
  "smidek@proglas.cz",
  "martin.smidek@gmail.com",
  "gandi@volny.cz",
  "martin.smidek@gmail.com",
  "error@smidek.eu",
  "martin.smidek@gmail.com",
  "martin@smidek.eu"
),
'vybor'=> array(
  "ymca@setkani.org",
  "cerny.vavrovice@seznam.cz",
  "j.kvapil@kvapil-elektro.cz",
  "svika.petr@seznam.cz",
  "martin@smidek.eu"
),
'konf'=> array(
  "2martin.kolar@gmail.com",
  "a.asana@seznam.cz",
  "a.m.malerovi@worldonline.cz",
  "abrahamovakatka@seznam.cz",
  "adamik@cbox.cz",
  "agi@volny.cz",
  "ajazbilovic@seznam.cz",
  "akasicky@csas.cz",
  "al.kubik@volny.cz",
  "alena.zmrzla@seznam.cz",
  "alorenc@nbox.cz",
  "ambrozek.ladislav@muhodonin.cz",
  "ambrozek@quick.cz",
  "anka@volny.cz",
  "anna.bouskova@centrum.cz",
  "annasodomkova@seznam.cz",
  "annastrzelcova@centrum.cz",
  "---anneli@portman.it",
  "antonin.koudelka@seznam.cz",
  "antonin.tesacek@seznam.cz",
  "arnost.bass@volny.cz",
  "artpetra@artpetra.cz",
  "audiodan@centrum.cz",
  "babicek@centroprojekt.cz",
  "bajapavlaskova@seznam.cz",
  "---balazia@eurolex.sk",
  "bammarkovi@quick.cz",
  "barta@cbnet.cz",
  "Bartova.Blanka@seznam.cz",
  "---BAUR@proglas.cz",
  "---bbrezina@gity.cz",
  "bednar@datasys.cz",
  "beranci@seznam.cz",
  "---berankova@proglas.cz",
  "bernardchrastecky@centrum.cz",
  "betak@burgmann.com",
  "bezouska.v@seznam.cz",
  "bhorka@ksoud.unl.justice.cz",
  "blanka@kubicekairtex.cz",
  "blazek.iv@atlas.cz",
  "---blaziova@nextra.sk",
  "bocan@trakce.cz",
  "bohca@post.cz",
  "brothanek@seznam.cz",
  "btriska@zpmvcr.cz",
  "bucek@fem.cz",
  "bujnovsky@quick.cz",
  "bures.j@mujbox.cz",
  "---bystrik.sliace@post.sk",
  "cagala@volny.cz",
  "canda.stefan@seznam.cz",
  "castul@seznam.cz",
  "cerny.vavrovice@seznam.cz",
  "cerny@proglas.cz",
  "cestr@knihkrnov.cz",
  "cichonjosef@seznam.cz",
  "cpr@doo.cz",
  "cprop@doo.cz",
  "cprvyzva@doo.cz",
  "d.gebauer@email.cz",
  "dag.blahova@seznam.cz",
  "dag123@centrum.cz",
  "dagmar.kolarova@gmail.com",
  "dagmar.wormova@seznam.cz",
  "dagmar_foltova@centrum.cz",
  "dalimil.barton@meac.cz",
  "danahalbrstatova@tiscali.cz",
  "darina.brothankova@seznam.cz",
  "das.dental@tiscali.cz",
  "devetter@upb.cas.cz",
  "didimos@seznam.cz",
  "dobrakniha@wo.cz",
  "dolezal.jura@seznam.cz",
  "dolezelova@biskupstvi.cz",
  "doming@volny.cz",
  "dvorak.1968@tiscali.cz",
  "dvorak@mikulovice.cz",
  "e.mercl@seznam.cz",
  "editmoravcova@email.cz",
  "eduard.strzelec@centrum.cz",
  "educos@mbox.vol.cz",
  "---ekondela@izaqua.sk",
  "---ekonomix_hujo@nextra.sk",
  "emgkl@tiscali.cz",
  "erika.domasikova@caritas.cz",
  "erika.domasikova@tiscali.cz",
  "esvoboda@seznam.cz",
  "eva.merclova@centrum.cz",
  "evakalvinska@seznam.cz",
  "f.hellebrand@seznam.cz",
  "f.podskubka@vela.cz",
  "f.zajicek@med.muni.cz",
  "fanduli@atlas.cz",
  "---feketovam@pobox.sk",
  "fiser.projekce@quick.cz",
  "fmoravcik@satos.cz",
  "frantisek.ruzicka@mukrupka.cz",
  "frantisek.vomacka@unex.cz",
  "frantisekchvatik@muzlin.cz",
  "fuis@fme.vutbr.cz",
  "fuis@seznam.cz",
  "fusekle@centrum.cz",
  "gajdova@hlucin.cz",
  "gargalici@tiscali.cz",
  "genzerm@atlas.cz",
  "genzerovap@atlas.cz",
  "geocart@c-mail.cz",
  "georgeII@seznam.cz",
  "gita.vyletalova@email.cz",
  "glogar@centrum.cz",
  "gras_servis@post.cz",
  "grebikjosef@seznam.cz",
  "gregor.ludek@vol.cz",
  "gregor@cmg.prostejov.cz",
  "gutu@centrum.cz",
  "hajt.marketa@seznam.cz",
  "hamrikovi@volny.cz",
  "hana.dalikova@email.cz",
  "hana.malcova@centrum.cz",
  "hana.pistorova@familia.cz",
  "hana.michalcova@centrum.cz",
  "hannybuch@seznam.cz",
  "---hauserova@proglas.cz",
  "havelka.jirka@centrum.cz",
  "hazy@centrum.cz",
  "hcajankova@seznam.cz",
  "hej_rup@volny.cz",
  "helena.im@seznam.cz",
  "hesta@hesta.cz",
  "hlisnikovsky.j@seznam.cz",
  "HMa@seznam.cz",
  "hndh@volny.cz",
  "hnizda.kamil@seznam.cz",
  "horacek.buk@seznam.cz",
  "horakova.vlasta@seznam.cz",
  "horakovam@atlas.cz",
  "horakovia.h@email.cz",
  "horska@nettown.cz",
  "horsky@nettown.cz",
  "HOSEK.7@seznam.cz",
  "houskaj@post.cz",
  "HPerinova@seznam.cz",
  "hstefek@meding.cz",
  "hukovi@centrum.cz",
  "chemingstav@seznam.cz",
  "chrpa.pavla@seznam.cz",
  "i.nejezchlebova@quick.cz",
  "ificzka@seznam.cz",
  "info@ruff.cz",
  "ing.jiri.brtnik@seznam.cz",
  "ipro-pm@volny.cz",
  "irena.smekalova@centrum.cz",
  "iva.kasikova@centrum.cz",
  "iva-kasparkova@seznam.cz",
  "ivan.kolos@vsb.cz",
  "ivana.jenistova@caritas.cz",
  "ivanpodest@seznam.cz",
  "ivo.kalvinsky@seznam.cz",
  "j.babicek@seznam.cz",
  "j.brauner@volny.cz",
  "j.ejem@seznam.cz",
  "j.fidrmuc@volny.cz",
  "j.kvapil@kvapil-elektro.cz",
  "j.orlik@seznam.cz",
  "j.solovsky@centrum.cz",
  "j.v.zajickovi@tiscali.cz",
  "j.zelinka@centrum.cz",
  "jafra@quick.cz",
  "jakub.david@seznam.cz",
  "jan.bucha@quick.cz",
  "jan.eyer@centrum.cz",
  "jan.havlicek@spvs.cz",
  "jan.chrastecky@siemens.com",
  "jan.janoska@volny.cz",
  "jan.juran@volny.cz",
  "JAN.LOUCKA1959@seznam.cz",
  "jan.mantl@tiscali.cz",
  "jan.mikolas@volny.cz",
  "jan.petkov@opava.cz",
  "jan.rotter@seznam.cz",
  "jan.rychtar@tiscali.cz",
  "jan.straka@centrum.cz",
  "jana.jarolimova@email.cz",
  "jana.kaluzova@rwe.cz",
  "jana.praisova@doo.cz",
  "jana.vodakova@centrum.cz",
  "janajagerova@seznam.cz",
  "janakadr@centrum.cz",
  "janakopriva@seznam.cz",
  "janavcelova@centrum.cz",
  "janjager@seznam.cz",
  "jarekkriz@volny.cz",
  "jarholka@seznam.cz",
  "jarko@tiscali.cz",
  "jaro.slavte@seznam.cz",
  "jaromir.kvapil@iol.cz",
  "jaromir_sevela@rutronik.com",
  "---jatom@jatom.cz",
  "jelinekjosef@volny.cz",
  "jerabkovam@email.cz",
  "jforbelsky@ic-energo.cz",
  "jhutar@volny.cz",
  "Jidelna.MSUkrajinska@seznam.cz",
  "jindrich.honek@seznam.cz",
  "jiri.holik@volny.cz",
  "jiri.linart@centrum.cz",
  "jiri.malec@volny.cz",
  "jiri.satke@cewood.cz",
  "jiri.slimarik@volny.cz",
  "jiri.smahel@gmail.com",
  "tkac.jiri@inset.com",
  "jiri@doffek.cz",
  "jirkalachman@tiscali.cz",
  "jirky.peska@tiscali.cz",
  "jitahory@seznam.cz",
  "jitka_vodickova@kb.cz",
  "jitkakozak@seznam.cz",
  "jjgoth@razdva.cz",
  "jjsebek@volny.cz",
  "jkasicka@csas.cz",
  "JKristkova@ksoud.brn.justice.cz",
  "jkuncar@cpdirect.cz",
  "jledl@quick.cz",
  "jmalasek@volny.cz",
  "josef.cervenka@volny.cz",
  "josef.fritschka@technodat.cz",
  "josef.liberda@mujes.cz",
  "josef.mori@zdas.cz",
  "josef.neruda@dalkia.cz",
  "josefka.koutna@seznam.cz",
  "joshavlik@centrum.cz",
  "jprokopova@seznam.cz",
  "jslachtova@seznam.cz",
  "jura.stransky@seznam.cz",
  "---juraj.cerven@softec.sk",
  "jurankova@proglas.cz",
  "just.t@seznam.cz",
  "juty@seznam.cz",
  "jvpsimon@seznam.cz",
  "jzich@atlas.cz",
  "kabatovi@gmail.com",
  "kafonkova@centrum.cz",
  "kalabova@tiscali.cz",
  "kamajafr@quick.cz",
  "karel.audit@centrum.cz",
  "karel.bartos@centrum.cz",
  "Karel.Cyrus@fei.com",
  "karel.rysavy@post.cz",
  "kaspic@seznam.cz",
  "kastovskyj@seznam.cz",
  "katerina.remesova@seznam.cz",
  "katka.k@aschool.cz",
  "katkadol@seznam.cz",
  "kintrova@proglas.cz",
  "kk@brno.kdu.cz",
  "klaber@volny.cz",
  "klanov@seznam.cz",
  "Klasek.Robert@uhul.cz",
  "klimes@portal.cz",
  "Knopf.Stanislav@seznam.cz",
  "kodek.petruj@centrum.cz",
  "kodek@iol.cz",
  "kohoutek127@seznam.cz",
  "korbel@gvmyto.cz",
  "koronthalyova@seznam.cz",
  "kostrhon@seznam.cz",
  "kovo.sujan@seznam.cz",
  "kpejchalova@seznam.cz",
  "kreces1.edu@mail.cez.cz",
  "krizalkovicova@seznam.cz",
  "krizvlastimil@seznam.cz",
  "kstepanek@email.cz",
  "---kubes@adda.sk.",
  "---kufova@osobnifinanceplus.cz",
  "kuchar@flux.cz",
  "kulihrasek.jiri@seznam.cz",
  "kuncarovi@seznam.cz",
  "kutna.hora@cb.cz",
  "---kvapil@PSP.cz",
  "kvapilovajaroslava@seznam.cz",
  "l.danys@centrum.cz",
  "l.Kabatova@quick.cz",
  "---lacop@merina.sk",
  "lachman@msmt.cz",
  "leni25@seznam.cz",
  "lenihandzlova@centrum.cz",
  "lenka.ryzova@centrum.cz",
  "lenka_sevelova@post.cz",
  "lesakova.ls157@lesycr.cz",
  "lhorsak@itczlin.cz",
  "Libor.Jarolim@seznam.cz",
  "libor.kabat@power.alstom.com",
  "libuse.popelkova@seznam.cz",
  "lidajetlebova@seznam.cz",
  "lidkacerna@seznam.cz",
  "limail@seznam.cz",
  "limramovsky@nbox.cz",
  "---ljarolim@elmath.cz",
  "Ljuba.Stranska@seznam.cz",
  "lmp@volny.cz",
  "lnenicka.Jiri@seznam.cz",
  "louckova.marie@seznam.cz",
  "lraus@tenza.cz",
  "lubomir@cmail.cz",
  "lucie.borakova@seznam.cz",
  "ludek@bouska.info",
  "ludmila.liberdova@mujes.cz",
  "Ludmila.Lnenickova@seznam.cz",
  "ludmila.loksova@seznam.cz",
  "ludva@hegrlik.cz",
  "ludvikmichlovsky@seznam.cz",
  "lukl@iol.cz",
  "m.a.markova@volny.cz",
  "m.novotna@post.cz",
  "m.stula@worldonline.cz",
  "m.tvrda@seznam.cz",
  "m.zelinkova@centrum.cz",
  "maba@o2active.cz",
  "majka.feketeova@gmail.com",
  "majkace@volny.cz",
  "majkasimonova@seznam.cz",
  "makovnici@zrnka.net",
  "marcbo@seznam.cz",
  "Marcela.Hoskova@seznam.cz",
  "marek.milan@centrum.cz",
  "marek.pospisil@seznam.cz",
  "marek_janca@quick.cz",
  "marie.wawraczova@volny.cz",
  "martamo@seznam.cz",
  "martin.busina@seznam.cz",
  "martin.cajanek@osu.cz",
  "martin.ds@seznam.cz",
  "martin.chromjak@tiscali.cz",
  "martin@smidek.eu",
  "martina.babickova@seznam.cz",
  "---martinek@pmgastro.cz",
  "martinka.koudelkova@seznam.cz",
  "martinka.petr@seznam.cz",
  "martinka.stepanek@olomouc.cz",
  "---marusiak@sponit.cz",
  "mbrez@seznam.cz",
  "medium@email.cz",
  "metodej.chrastecky@seznam.cz",
  "mezulanik@proglas.cz",
  "mholdik@volny.cz",
  "mholikova@volny.cz",
  "mhubacekza@volny.cz",
  "michal@garden114.cz",
  "michalec.zdenek@inset.com",
  "mika@diamo.cz",
  "mila.havrdova@seznam.cz",
  "---milada.barotova@racek.org",
  "milada.n@centrum.cz",
  "milan.barot@gmail.com",
  "---milan.barot@racek.org",
  "milan.bily@volny.cz",
  "milan.duben@gist.cz",
  "milan.jebavy@tiscali.cz",
  "milan.kantor@quick.cz",
  "milan.strakos@click.cz",
  "milansoldan@muzlin.cz",
  "milence@centrum.cz",
  "milos.vyletal@email.cz",
  "miloslav.kopriva@svi.hk.ds.mfcr.cz",
  "mira.svec@wo.cz",
  "mirek@kadrnozka.cz",
  "mirek_dvorak@volny.cz",
  "mirekp@tiscali.cz",
  "mirf@volny.cz",
  "miroslav.borak@T-mobile.cz",
  "miroslav.sot@centrum.cz",
  "miroslav-kotek@seznam.cz",
  "MJarolim@seznam.cz",
  "---mkapustova@szm.sk",
  "mkotek@ic-energo.eu",
  "modry.slon@volny.cz",
  "moni.dol@volny.cz",
  "mpolak@centrum.cz",
  "mrazkova14@seznam.cz",
  "mrozek@techfloor.cz",
  "MSujanova@seznam.cz",
  "mudr.eyerova@volny.cz",
  "mv@martinvana.net",
  "nadabetakova@seznam.cz",
  "nagl@arcibol.cz",
  "necas@anete.com",
  "nedvedova.zdislava@pontis.cz",
  "nejez@seznam.cz",
  "nejezchleb@crytur.cz",
  "nerudv1@feld.cvut.cz",
  "norin@email.cz",
  "novak@ivysehrad.cz",
  "novakpm@volny.cz",
  "novotny.p@kr-ustecky.cz",
  "ohral@iol.cz",
  "ohralova@email.cz",
  "oldtom@t-email.cz",
  "olgaolivova@centrum.cz",
  "ondracekm@seznam.cz",
  "ondranicka@seznam.cz",
  "ondrej.mrazek@schiedel.cz",
  "ondrejremes@atlas.cz",
  "osikora@seznam.cz",
  "oslama@fnbrno.cz",
  "oto.worm@seznam.cz",
  "p.e.t.r.f@seznam.cz",
  "p.folta@centrum.cz",
  "p.janoskova@seznam.cz",
  "p.kvapil@kvapil-elektro.cz",
  "p.ne@seznam.cz",
  "p.patterman@centrum.cz",
  "p_blaha@kb.cz",
  "pa.vaclav@seznam.cz",
  "palisek@bnzlin.cz",
  "pase@seznam.cz",
  "pastor@marianskohorska.cz",
  "pavcerny@volny.cz",
  "pavel.folta@charita.cz",
  "pavel.chladek@vasbo.cz",
  "pavel.klimes@email.cz",
  "pavel.kyska@volny.cz",
  "pavel.nemec@zs-majakovskeho.cz",
  "pavel.obluk@dchoo.caritas.cz",
  "pavel.samek@mora.cz",
  "pavel.smolka@post.cz",
  "pavel.vagunda@atlas.cz",
  "pavel.vit@tycoelectronics.com",
  "pavel@pneuprochazka.cz",
  "pavelcejnek@seznam.cz",
  "pavelsevcik76@seznam.cz",
  "pavelsmolko@yahoo.com",
  "pavelzeleny@seznam.cz",
  "pavla.rybova@caritas.cz",
  "pavla1.ticha@seznam.cz",
  "pavlik@intext.cz",
  "pavlinahajna@centrum.cz",
  "pdaniela@centrum.cz",
  "pebursik@volny.cz",
  "pek@redis.cz",
  "pepa.ondracek@seznam.cz",
  "pepethesailor@volny.cz",
  "pesek@it.cas.cz",
  "peta.dolik@seznam.cz",
  "peter.telekes@post.cz",
  "peterescu@seznam.cz",
  "---petlanova@proglas.cz",
  "petr.brich@quick.cz",
  "petr.d@volny.cz",
  "Petr.Janda@centrum.cz",
  "petr.klasek@seznam.cz",
  "petr.otr@worldonline.cz",
  "petr.schlemmer@nemspk.cz",
  "petr.schlemmer@seznam.cz",
  "petr.wajda@centrum.cz",
  "petr_bezpalec@volny.cz",
  "petra.vin@seznam.cz",
  "petra@doffek.cz",
  "petra@ibp.cz",
  "petrprokop@seznam.cz",
  "Petsti@email.cz",
  "pgadas@razdva.cz",
  "pgholubovi@iol.cz",
  "phranac@seznam.cz",
  "pchalenka@seznam.cz",
  "pilnam@seznam.cz",
  "pilny.spp@volny.cz",
  "pipvovo@mail.ru",
  "pjhlustik@gmail.com",
  "PLECHACP@fnplzen.cz",
  "polak@synerga.cz",
  "---polakovicovci@chello.sk",
  "policer@seznam.cz",
  "ponizil@agritec.cz",
  "ponizil@salvo.zlin.cz",
  "---portman@promo.it",
  "ppejchal@seznam.cz",
  "ppodsednik@razdva.cz",
  "ppr@doo.cz",
  "---prais@premie.cz",
  "premek.hruby@centrum.cz",
  "PriessnitzJan@seznam.cz",
  "prihodova@inform.cz",
  "proenvi@proenvi.cz",
  "prochazkovak@email.cz",
  "pstefkova@meding.cz",
  "pstoklasa@krok-hranice.cz",
  "ptacnik@taxnet.cz",
  "pwawracz@volny.cz",
  "r.b@seznam.cz",
  "r.barabas@seznam.cz",
  "r.hadraba@seznam.cz",
  "r.komarek@volny.cz",
  "radim.sotkovsky@siemens.com",
  "RadovanHolik@seznam.cz",
  "rakhana@quick.cz",
  "raksim@centrum.cz",
  "rasticova@volny.cz",
  "---rastislav.pocubay@st.nicolaus.sk",
  "RausovaRut@seznam.cz",
  "rbrazda@infotech.cz",
  "rcajanek@seznam.cz",
  "rek@seznam.cz",
  "rhodesian@centrum.cz",
  "rlap@volny.cz",
  "rodina.tomsova@worldonline.cz",
  "---rodina@arcibol.",
  "roman.mokrosz@post.cz",
  "roman.zima@tiscali.cz",
  "rosovaradka@seznam.cz",
  "rostislav.kulisan@cirkevnizs.hradecnm.indos.cz",
  "rp.barton@seznam.cz",
  "rpavelkova@dpmb.cz",
  "ruckovi@seznam.cz",
  "rybasvatopluk@seznam.cz",
  "s.stranak@quick.cz",
  "sandholzova@seznam.cz",
  "sapak.vojtech@volny.cz",
  "saranch@seznam.cz",
  "sdostal@doo.cz",
  "seifriedovi@seznam.cz",
  "selucka.monika@seznam.cz",
  "sequens@seznam.cz",
  "sevros@centrum.cz",
  "schnirch@volny.cz",
  "simerda@vues.cz",
  "simici@mybox.cz",
  "simtec@post.cz",
  "sintal@izolacezlin.cz",
  "siskovi@centrum.cz",
  "skoloud.p@seznam.cz",
  "skrlata@tiscali.cz",
  "sladek@opr.ova.cd.cz",
  "slachtajan@seznam.cz",
  "slavomir.mrozek@seznam.cz",
  "slivka@signalbau.cz",
  "smidek@proglas.cz",
  "smidkova@proglas.cz",
  "snejdar@seznam.cz",
  "sobechleby@seznam.cz",
  "solano@centrum.cz",
  "soptikkamil@post.cz",
  "sotkovskyr@centrum.cz",
  "sotola@hlinsko.cz",
  "sotovi@tiscali.cz",
  "soubusta@sloup.upol.cz",
  "soucek@vukrom.cz",
  "srandyskova@seznam.cz",
  "srandyskova@seznam.cz",
  "srsnovi@email.cz",
  "st.mach@volny.cz",
  "---stacho2@tele2.cz",
  "standa.skricka@volny.cz",
  "stanek.p@volny.cz",
  "stary.misa@quick.cz",
  "stevenix@seznam.cz",
  "stoklaskovi.tas@seznam.cz",
  "strakova.misa@centrum.cz",
  "sujanovaZora@seznam.cz",
  "sujanovi@tiscali.cz",
  "svarservis@svarservis.cz",
  "sypena@quick.cz",
  "t.jakubicek@seznam.cz",
  "tetra.jurka@seznam.cz",
  "tholik@volny.cz",
  "---tichy@ornela.cz",
  "tomaluk@centrum.cz",
  "tomas@vichr.net",
  "tomis@kvados.cz",
  "tomsarnost@seznam.cz",
  "tomsvob@med.muni.cz",
  "tondastrnad@volny.cz",
  "trdlicka@tiscali.cz",
  "tschoster@seznam.cz",
  "uca@seznam.cz",
  "uhlirovi.vlcice@wo.cz",
  "v.art@seznam.cz",
  "v.paliskova@centrum.cz",
  "vaclav.tymocko@seznam.cz",
  "vaclav.wagner@degu.cz",
  "vaclavsky@mujmejl.cz",
  "vapch@seznam.cz",
  "VCurylo@seznam.cz",
  "vejmelek@yahoo.com",
  "vendula.zimova@volny.cz",
  "veraschlemmerova@seznam.cz",
  "vhana@iol.cz",
  "vhranacova@seznam.cz",
  "vit.albrecht@cmail.cz",
  "vit.grec@tiscali.cz",
  "vit.hamala@kleibl.cz",
  "vit.stepanek@olomouc.cz",
  "vitezslavkares@medatron.cz",
  "vitnec@seznam.cz",
  "vkoronthaly@seznam.cz",
  "vlacilpavel@seznam.cz",
  "vladimirvecera@email.cz",
  "---vlado.zelik@apsoft.sk",
  "vlastuse@centrum.cz",
  "vlcek@vz.cz",
  "vodak@familycoaching.cz",
  "vojtech.brazdil@cmss-oz.cz",
  "vojtech.vrana@hella.com",
  "vojtechryza@quick.cz",
  "---vojtekj@piar.gtn.sk",
  "vrandysek@tiscali.cz",
  "vrandysek@tiscali.cz",
  "vuk@email.cz",
  "we.805@bauhaus.cz",
  "ymca@setkani.org",
  "Z.Krtek@seznam.cz",
  "zaboj@arcibol.cz",
  "zbynek.d@email.cz",
  "zbynek.kral@tiscali.cz",
  "zdena@hegrlik.cz",
  "zdenek.sychra@mybox.cz",
  "zdrahal@pod.cz",
  "zhabr@csas.cz",
  "zpetruj@qgir.cz",
  "zuzana.kolosova@seznam.cz",
  "zuzana.kostrhonova@seznam.cz",
  "zuzka.vlcek@seznam.cz",
  "zverinovi@raz-dva.cz"
),
'jarda'=> array(
  "1daf@seznam.cz",
  "2martin.kolar@seznam.cz",
  "a.asana@seznam.cz",
  "a.m.malerovi@worldonline.cz",
  "adamik@cbox.cz",
  "ajazbilovic@seznam.cz",
  "akasicky@csas.cz",
  "al.kubik@volny.cz",
  "alena.zmrzla@seznam.cz",
  "alenahusakova@seznam.cz",
  "alorenc@nbox.cz",
  "angio@vol.cz",
  "anka@volny.cz",
  "anna.eis@post.cz",
  "anna.eyerova@seznam.cz",
  "annastrzelcova@centrum.cz",
  "antonin.koudelka@seznam.cz",
  "antonin.tesacek@seznam.cz",
  "audiodan@centrum.cz",
  "babicek@centroprojekt.cz",
  "bajapavlaskova@seznam.cz",
  "bammarkovi@quick.cz",
  "Bartova.Blanka@seznam.cz",
  "Bartova.Blanka@seznam.cz",
  "---bbrezina@gity.cz",
  "bednar@datasys.cz",
  "bednarikoval@seznam.cz",
  "Belmondo1@seznam.cz",
  "beranci@seznam.cz",
  "Bernadetta@email.cz",
  "bezouska.v@seznam.cz",
  "blahmarie@centrum.cz",
  "blanka@kubicekairtex.cz",
  "blazek.iv@atlas.cz",
  "bohca@post.cz",
  "bohunka_jirka@volny.cz",
  "bonaventura@kapucini.cz",
  "Bortel@alve.cz",
  "brothanek@seznam.cz",
  "btriska@zpmvcr.cz",
  "bucek@fem.cz",
  "bures.j@mujbox.cz",
  "cagala@volny.cz",
  "castul@seznam.cz",
  "cerny.vavrovice@seznam.cz",
  "cerny@proglas.cz",
  "cichonjosef@seznam.cz",
  "cpr@doo.cz",
  "cprop@doo.cz",
  "cprvyzva@doo.cz",
  "cyrusova@gmail.cz",
  "dag.blahova@seznam.cz",
  "dag123@centrum.cz",
  "dagmar.kolarova@gmail.com",
  "Dagmar.Sera@seznam.cz",
  "dagmar.wormova@seznam.cz",
  "dagmar_foltova@centrum.cz",
  "dalimil.barton@meac.cz",
  "dana@hydahesi.cz",
  "daniel.bednarik@seznam.cz",
  "daniel@exo.cz",
  "darina.brothankova@seznam.cz",
  "devetter@upb.cas.cz",
  "dobes-pavel@seznam.cz",
  "dobrakniha@wo.cz",
  "dobrovolny@biskupstvi.cz",
  "dolezal.jura@seznam.cz",
  "dolezal.jura@seznam.cz",
  "dolezelova@biskupstvi.cz",
  "doming@volny.cz",
  "Drimalova.Marie@seznam.cz",
  "---dům@setkani.org,katka.k@aschool.cz",
  "dvorak@mikulovice.cz",
  "e.mercl@seznam.cz",
  "editmoravcova@email.cz",
  "eduard.strzelec@centrum.cz",
  "educos@mbox.vol.cz",
  "---ekonomix_hujo@nextra.sk",
  "emgkl@tiscali.cz",
  "emil_vodicka@kb.cz",
  "EmilieZichova@seznam.cz",
  "erika.domasikova@caritas.cz",
  "erika.domasikova@tiscali.cz",
  "esvoboda@seznam.cz",
  "eva.merclova@centrum.cz",
  "eva.pazourkova@seznam.cz",
  "evahut@seznam.cz",
  "evakalvinska@seznam.cz",
  "evanevolova@seznam.cz",
  "f.hellebrand@seznam.cz",
  "f.zajicek@med.muni.cz",
  "fanduli@atlas.cz",
  "fara.zeleznice@centrum.cz",
  "fara.zeleznice@centrum.cz",
  "farnost@sdb.cz",
  "---feketovam@pobox.sk",
  "fiser.projekce@quick.cz",
  "fmoravcik@satos.cz",
  "frantisek.koudelka@post.cz",
  "frantisek.vomacka@unex.cz",
  "frantisekchvatik@muzlin.cz",
  "fryc@pmbs.cz",
  "fuis@fme.vutbr.cz",
  "fuis@seznam.cz",
  "fuisova.miroslava@brno.cz",
  "fusekle@centrum.cz",
  "gargalici@tiscali.cz",
  "genzerm@atlas.cz",
  "genzerovap@atlas.cz",
  "geocart@c-mail.cz",
  "georgeII@seznam.cz",
  "gita.vyletalova@email.cz",
  "glogar@centrum.cz",
  "glogar@odry.cz",
  "grebikjosef@seznam.cz",
  "gregor.ludek@vol.cz",
  "gregor@cmg.prostejov.cz",
  "gutu@centrum.cz",
  "h.vyslouzilova@centrum.cz",
  "hajt.marketa@seznam.cz",
  "hamrikovi@volny.cz",
  "hana.dalikova@email.cz",
  "hana.malcova@centrum.cz",
  "hana.pistorova@familia.cz",
  "hanadrahomir@seznam.cz",
  "hanka.barankova@centrum.cz",
  "hanka.brichova@centrum.cz",
  "hannybuch@seznam.cz",
  "havelka.jirka@centrum.cz",
  "hazy@centrum.cz",
  "hcajankova@seznam.cz",
  "hej_rup@volny.cz",
  "hesta@hesta.cz",
  "himramovska@gmail.com",
  "HMa@seznam.cz",
  "hndh@volny.cz",
  "hnizda.kamil@seznam.cz",
  "horacek.buk@seznam.cz",
  "horacek@mendelu.cz",
  "horakova.vlasta@seznam.cz",
  "horakovia.h@email.cz",
  "hosek.7@seznam.cz",
  "hostickova.j@seznam.cz",
  "houskaj@post.cz",
  "HPerinova@seznam.cz",
  "hstefek@meding.cz",
  "cho.zdislava@caritas.cz",
  "chrpa.pavla@seznam.cz",
  "---i.šalek@kvapil-elektro.cz",
  "ificzka@seznam.cz",
  "info@ruff.cz",
  "ing.jiri.brtnik@seznam.cz",
  "irena.hi@centrum.cz",
  "irena.smekalova@centrum.cz",
  "iva-kasparkova@seznam.cz",
  "ivan.kolos@seznam.cz",
  "ivana.jenistova@caritas.cz",
  "ivanamatusu@seznam.cz",
  "ivanpodest@seznam.cz",
  "ivo.kalvinsky@seznam.cz",
  "j.babicek@seznam.cz",
  "j.baletka@kvapil-elektro.cz",
  "j.brauner@volny.cz",
  "j.fidrmuc@volny.cz",
  "j.kordik1@tiscali.cz",
  "j.kordik1@tiscali.cz",
  "j.kvapil@kvapil-elektro.cz",
  "j.kvetakova@seznam.cz",
  "j.orlik@seznam.cz",
  "j.solovsky@centrum.cz",
  "j.zelinka@centrum.cz",
  "jafra@quick.cz",
  "jakubectruhlarstvi@seznam.cz",
  "jan.eis@post.cz",
  "jan.eyer@centrum.cz",
  "jan.havlicek@spvs.cz",
  "jan.chrastecky@siemens.com",
  "jan.janoska@volny.cz",
  "jan.juran@velkabystrice.cz",
  "JAN.LOUCKA1959@seznam.cz",
  "jan.mantl@tiscali.cz",
  "jan.mikolas@volny.cz",
  "jan.rotter@seznam.cz",
  "jan.straka@centrum.cz",
  "jana.jarolimova@email.cz",
  "jana.steinocherova@vzp.cz",
  "jana.tobolikova@seznam.cz",
  "jana.vodakova@centrum.cz",
  "jana@svika.eu",
  "janajagerova@seznam.cz",
  "janakadr@centrum.cz",
  "janakopriva@seznam.cz",
  "janavcelova@centrum.cz",
  "Janda.Jakub@seznam.cz",
  "jane396@email.cz",
  "janjager@seznam.cz",
  "---jankoatonka@centrum.sk",
  "jarekkriz@volny.cz",
  "jaro.slavte@seznam.cz",
  "jaromir.kvapil@iol.cz",
  "jaromir_sevela@rutronik.com",
  "jaroslava.randyskova@seznam.cz",
  "jelinekjosef@volny.cz",
  "jerabkovam@email.cz",
  "jforbelsky@ic-energo.cz",
  "jhutar@volny.cz",
  "jic-havlikova@centrum.cz",
  "jidelna.msukrajinska@seznam.cz",
  "jindra.sandmark@kolumbus.fi",
  "jindra.sandmark@kolumbus.fi",
  "jindrich.honek@seznam.cz",
  "jiri.holik@volny.cz",
  "jiri.linart@centrum.cz",
  "jiri.malec@volny.cz",
  "jiri.satke@cewood.cz",
  "jiri.slimarik@volny.cz",
  "jiri.smahel@gmail.com",
  "jiri.stuchly@vitkovice.cz",
  "jiri@doffek.cz",
  "jirkalachman@tiscali.cz",
  "jirky.peska@tiscali.cz",
  "jitahory@seznam.cz",
  "jitka_vodickova@kb.cz",
  "jjgoth@razdva.cz",
  "jjsebek@volny.cz",
  "jjvavrovi@centrum.cz",
  "jkasicka@csas.cz",
  "JKristkova@ksoud.brn.justice.cz",
  "jkuncar@cpdirect.cz",
  "jledl@quick.cz",
  "jmaisnerova@seznam.cz",
  "jmalasek@volny.cz",
  "josef.cervenka@volny.cz",
  "josef.hutar@golemfinance.cz",
  "josef.liberda@mujes.cz",
  "josef.neruda@dalkia.cz",
  "josef_havlik@centrum.cz",
  "josefka.koutna@seznam.cz",
  "joshavlik@centrum.cz",
  "jprokopova@seznam.cz",
  "jslachtova@seznam.cz",
  "jura.stransky@seznam.cz",
  "jurankova@proglas.cz",
  "just.t@seznam.cz",
  "just@seznam.cz",
  "juty@seznam.cz",
  "jvpsimon@seznam.cz",
  "jzich@atlas.cz",
  "k.stepanek@email.cz",
  "ka.urbanova@seznam.cz",
  "kabatovi@gmail.com",
  "kaja@skritci.com",
  "kalabova@tiscali.cz",
  "karel.audit@centrum.cz",
  "karel.bartos@centrum.cz",
  "karel.rysavy@post.cz",
  "kaspic@seznam.cz",
  "katerina.remesova@seznam.cz",
  "katkadol@seznam.cz",
  "katkadol@seznam.cz",
  "kintrova@proglas.cz",
  "klanov@seznam.cz",
  "Klasek.Robert@uhul.cz",
  "klihavcovi@seznam.cz",
  "kmj.friedl@seznam.cz",
  "Knopf.Stanislav@seznam.cz",
  "kodek.petruj@centrum.cz",
  "kodek@iol.cz",
  "kohoutek127@seznam.cz",
  "komfort-jc@seznam.cz",
  "kostrhon@seznam.cz",
  "kpejchalova@seznam.cz",
  "krajca.f@seznam.cz",
  "kreces1.edu@mail.cez.cz",
  "krejc.rce@seznam.cz",
  "kristkovajana@seznam.cz",
  "krizalkovicova@seznam.cz",
  "krizvlastimil@seznam.cz",
  "krsekjaroslav@seznam.cz",
  "kstepanek@gity.cz",
  "kulihrasek.jiri@seznam.cz",
  "kutna.hora@cb.cz",
  "kvapilovajaroslava@seznam.cz",
  "kytienka@seznam.cz",
  "l.hudcova@gmail.com",
  "l.Kabatova@qmail.cz",
  "---lacop@merina.sk",
  "lachman@msmt.cz",
  "lakosilova@laksmanna.cz",
  "lakosilova@laksmanna.cz",
  "lancelot@demdaal.cz",
  "leni25@seznam.cz",
  "lenka.ryzova@centrum.cz",
  "lenka_sevelova@post.cz",
  "lenkadrabek@centrum.cz",
  "Lhrad@seznam.cz",
  "---libuse.fiserova@unimilts.cz",
  "lidajetlebova@seznam.cz",
  "lidkacerna@seznam.cz",
  "liduska127@seznam.cz",
  "limail@seznam.cz",
  "limramovsky@gmail.com",
  "Ljuba.Stranska@seznam.cz",
  "lmp@volny.cz",
  "lnenicka.Jiri@seznam.cz",
  "louckova.marie@seznam.cz",
  "lraus@tenza.cz",
  "ltyrnerova@seznam.cz",
  "lubomir.zacek@rwe.cz",
  "lubomir@cmail.cz",
  "lucie.borakova@seznam.cz",
  "ludmila.liberdova@mujes.cz",
  "Ludmila.Lnenickova@setkani.org",
  "ludmila.loksova@seznam.cz",
  "ludva@hegrlik.cz",
  "ludvikmichlovsky@seznam.cz",
  "lukcas@seznam.cz",
  "lukl@iol.cz",
  "m.a.markova@volny.cz",
  "m.stula@worldonline.cz",
  "m.tvrda@seznam.cz",
  "m.zelinkova@centrum.cz",
  "maba@o2active.cz",
  "majka.feketeova@gmail.com",
  "majkace@volny.cz",
  "majkasimonova@seznam.cz",
  "makoudelkova@seznam.cz",
  "mamb@seznam.cz",
  "Marcela.Hoskova@seznam.cz",
  "marek.milan@centrum.cz",
  "marek.pospisil@seznam.cz",
  "marek_janca@quick.cz",
  "maria.cerna@seznam.cz",
  "marie.sevcik@seznam.cz",
  "marie.stavinohova@gmail.com",
  "marie.wawraczova@volny.cz",
  "mariedanek@centrum.cz",
  "mariegajduskova@gmail.com",
  "mariekanovska@seznam.cz",
  "market.dvorakova@gmail.cz",
  "marketa.vit@email.cz",
  "martaluzarova@seznam.cz",
  "martamo@seznam.cz",
  "martin.busina@seznam.cz",
  "martin.cajanek@osu.cz",
  "martin.chromjak@tiscali.cz",
  "martin@smidek.eu",
  "martina.babickova@seznam.cz",
  "martina.friedlova@upol.cz",
  "---martinek@pmgastro.cz",
  "martinka.koudelkova@seznam.cz",
  "martinka.petr@seznam.cz",
  "martinka.stepanek@olomouc.cz",
  "MartinVareka@seznam.cz",
  "MartinVareka@seznam.cz",
  "mbcko@centrum.cz",
  "medium@email.cz",
  "mezulanik@proglas.cz",
  "mfridrichova@prorodiny.cz",
  "mgraffinger@gmail.com",
  "mholikova@volny.cz",
  "mhubacekza@volny.cz",
  "michal@garden114.cz",
  "michalcova.hana@centrum.cz",
  "michalec.zdenek@inset.com",
  "mika@diamo.cz",
  "milada.barotova@gmail.com",
  "Milada.bortlova@cpzp.cz",
  "milada.maliskova@centrum.cz",
  "milada.n@centrum.cz",
  "milan.barot@gmail.com",
  "milan.bily@volny.cz",
  "milan.duben@gist.cz",
  "milan.jebavy@tiscali.cz",
  "milan.svojanovsky@seznam.cz",
  "milana.vykydalova@centrum.cz",
  "milansoldan@muzlin.cz",
  "milenapchalkova@seznam.cz",
  "milence@centrum.cz",
  "milos.vyletal@email.cz",
  "mira.svec@wo.cz",
  "mirek@kadrnozka.cz",
  "mirek_dvorak@volny.cz",
  "mirf@volny.cz",
  "miriam.louckova@seznam.cz",
  "miroslav.borak@T-mobile.cz",
  "miroslav.sot@centrum.cz",
  "miroslav-kotek@seznam.cz",
  "MJarolim@seznam.cz",
  "mkotek@ic-energo.eu",
  "modry.slon@volny.cz",
  "moni.dol@volny.cz",
  "mosikora@centrum.cz",
  "mpolak@centrum.cz",
  "mrazek.ondra@seznam.cz",
  "mrazkova14@seznam.cz",
  "mrozek@techfloor.cz",
  "mrozkova.agata@seznam.cz",
  "MSujanova@seznam.cz",
  "mujbracha@gmail.com",
  "mv@martinvana.net",
  "mzatecky@orcz.cz",
  "mzatecky@seznam.cz",
  "nagl@arcibol.cz",
  "necas@anete.com",
  "nedvedova.zdislava@pontis.cz",
  "nemec_pavel@hotmail.com",
  "nerudv1@feld.cvut.cz",
  "norin@email.cz",
  "novakpm@volny.cz",
  "novotny_jena@centrum.cz",
  "novsla@seznam.cz",
  "ohral@iol.cz",
  "ohralova@email.cz",
  "oldtom@t-email.cz",
  "olgaolivova@centrum.cz",
  "ondracekm@seznam.cz",
  "ondranicka@seznam.cz",
  "ondrej.mrazek@schiedel.cz",
  "ondrejremes@atlas.cz",
  "osikora@seznam.cz",
  "oslama@fnbrno.cz",
  "oto.worm@seznam.cz",
  "p.braunerova@seznam.cz",
  "p.folta@centrum.cz",
  "p.hudec@email.cz",
  "p.janoskova@seznam.cz",
  "p.kvapil@kvapil-elektro.cz",
  "p.ne@seznam.cz",
  "p_blaha@kb.cz",
  "pa.vaclav@seznam.cz",
  "pase@seznam.cz",
  "pavcerny@volny.cz",
  "pavel.fiser@ubcz.cz",
  "pavel.kyska@volny.cz",
  "pavel.obluk@dchoo.caritas.cz",
  "pavel.vagunda@atlas.cz",
  "pavel.vanicek@gsagency.cz",
  "pavel.vit@tycoelectronics.com",
  "pavel@pneuprochazka.cz",
  "pavelcejnek@seznam.cz",
  "paveldobe@seznam.cz",
  "pavelhranac@gmail.com",
  "pavel-ryska@centrum.cz",
  "pavelsevcik76@seznam.cz",
  "pavelsmolko@yahoo.com",
  "pavla.rybova@caritas.cz",
  "pavla1.ticha@seznam.cz",
  "pavlik@intext.cz",
  "pavlinahajna@centrum.cz",
  "pdaniela@centrum.cz",
  "pebursik@volny.cz",
  "pek@redis.cz",
  "pepa.ondracek@seznam.cz",
  "pesek@it.cas.cz",
  "peta.dolik@seznam.cz",
  "peterescu@seznam.cz",
  "petr.brich@centrum.cz",
  "petr.d@volny.cz",
  "Petr.Janda@centrum.cz",
  "petr.klasek@seznam.cz",
  "petr.otr@worldonline.cz",
  "petr.polansky@email.cz",
  "petr.schlemmer@nemspk.cz",
  "petr.schlemmer@seznam.cz",
  "petr.wajda@centrum.cz",
  "petr@skritci.com",
  "petr_bezpalec@volny.cz",
  "petra.krupickova@seznam.cz",
  "petra.vin@seznam.cz",
  "petra.vyn@centrum.cz",
  "petra@doffek.cz",
  "petra@ibp.cz",
  "petrkvetak@seznam.cz",
  "petrmatula@atlas.cz",
  "petrprokop@seznam.cz",
  "Petsti@email.cz",
  "pgadas@razdva.cz",
  "pchalek.tomas@seznam.cz",
  "pchalenka@seznam.cz",
  "pilnam@seznam.cz",
  "pilny.spp@volny.cz",
  "pipvovo@mail.ru",
  "PLECHACP@fnplzen.cz",
  "polak@synerga.cz",
  "policer@seznam.cz",
  "ponizil@agritec.cz",
  "ponizil@salvo.zlin.cz",
  "porkertova@seznam.cz",
  "ppejchal@seznam.cz",
  "ppr@doo.cz",
  "---prais@premie.cz",
  "PriessnitzJan@seznam.cz",
  "prihojana@seznam.cz",
  "prochazkova.petra@volny.cz",
  "prochazkovak@email.cz",
  "psmola@email.cz",
  "pstefkova@meding.cz",
  "pstoklasa@krok-hranice.cz",
  "ptacnik@taxnet.cz",
  "putzlachers@centrum.cz",
  "pvaclav@centrum.cz",
  "pwawracz@volny.cz",
  "r.b@seznam.cz",
  "r.hadraba@seznam.cz",
  "rad.dost@seznam.cz",
  "Radka.And@seznam.cz",
  "radka@fischerovi.cz",
  "radka_hazova@mik-bohemia.cz",
  "radonbob@seznam.cz",
  "RadovanHolik@seznam.cz",
  "rasticova@volny.cz",
  "---rastislav.pocubay@st.nicolaus.sk",
  "RausovaRut@seznam.cz",
  "rbrazda@infotech.cz",
  "rcajanek@seznam.cz",
  "rek@seznam.cz",
  "rene@fischerovi.eu",
  "rodina.tomsova@worldonline.cz",
  "rodiny.prerov@seznam.cz",
  "roman.mokrosz@post.cz",
  "roman.strossa@o2active.cz",
  "roman.strossa@o2active.cz",
  "rosovaradka@seznam.cz",
  "rostislav.kulisan@cirkevnizs.hradecnm.indos.cz",
  "rp.barton@seznam.cz",
  "rpavelkova@dpmb.cz",
  "ruckovi@seznam.cz",
  "rybasvatopluk@seznam.cz",
  "sakul208@email.cz",
  "sandholzova@seznam.cz",
  "sapak.vojtech@volny.cz",
  "saranch@seznam.cz",
  "sdostal@doo.cz",
  "seifriedovi@seznam.cz",
  "selucka.monika@seznam.cz",
  "sequens@seznam.cz",
  "sevros@centrum.cz",
  "shorackova@centrum.cz",
  "schnirch@volny.cz",
  "SimeckovaAndrea@seznam.cz",
  "simerda@vues.cz",
  "simona.hybsova@centrum.cz",
  "simtec@post.cz",
  "siskovi@centrum.cz",
  "skoloud.p@seznam.cz",
  "sladek@opr.ova.cd.cz",
  "slachtajan@seznam.cz",
  "slavomir.mrozek@seznam.cz",
  "slivka@signalbau.cz",
  "smidkova@proglas.cz",
  "snejdar@seznam.cz",
  "sojovejrizek@gmail.com",
  "solano@centrum.cz",
  "sotkovskyr@centrum.cz",
  "sotovi@tiscali.cz",
  "soubusta@sloup.upol.cz",
  "soucek@vukrom.cz",
  "srandyskova@seznam.cz",
  "srsnovi@email.cz",
  "ssmolova@email.cz",
  "stanek.p@volny.cz",
  "stanislav.foltyn@o2.com",
  "stastnik@volny.cz",
  "stastnikovah@seznam.cz",
  "stevenix@seznam.cz",
  "stoklaskovi.tas@seznam.cz",
  "strakova.misa@centrum.cz",
  "stredisko.catarina@centrum.cz",
  "stuchlamarie@seznam.cz",
  "stuchly.rudolf@seznam.cz",
  "stykar@mendelu.cz",
  "stykar@mendelu.cz",
  "sujan.michl@seznam.cz",
  "sujanovaZora@seznam.cz",
  "sujanovi@tiscali.cz",
  "svarservis@svarservis.cz",
  "svecjarda@atlas.cz",
  "svika.petr@seznam.cz",
  "sykorova7@seznam.cz",
  "szymikovi@volny.cz",
  "szymikovi@volny.cz",
  "terezie.gilgova@email.cz",
  "tetra.jurka@seznam.cz",
  "tholik@volny.cz",
  "tkacovi@seznam.cz",
  "tobolik.petr@seznam.cz",
  "tomaluk@centrum.cz",
  "tomas.urban@btm.cz",
  "tomas@vichr.net",
  "tomasgilg@seznam.cz",
  "tomecek.pavel@centrum.cz",
  "tomsvob@med.muni.cz",
  "tschoster@seznam.cz",
  "tykadlik@iex.cz",
  "uhlirovi.vlcice@wo.cz",
  "v.art@seznam.cz",
  "v.zdrahal@seznam.cz",
  "vaclav.marsik@bolid-m.cz",
  "vaclav.tymocko@seznam.cz",
  "vaclav.vacek@wo.cz",
  "vaclav.vacek@wo.cz",
  "vaclav.wagner@degu.cz",
  "vaclavsky@mujmejl.cz",
  "vanaHana@seznam.cz",
  "vapch@seznam.cz",
  "vavrajan@centrum.cz",
  "VCurylo@seznam.cz",
  "vecerova@arcibol.cz",
  "vejmelek@yahoo.com",
  "vera.zackova@eon.cz",
  "veraschlemmerova@seznam.cz",
  "vhana@iol.cz",
  "vhranacova@seznam.cz",
  "vit.albrecht@cmail.cz",
  "vit.stepanek@olomouc.cz",
  "vita.ham@seznam.cz",
  "vitezslava.sujanova@seznam.cz",
  "vitnec@seznam.cz",
  "vlacilpavel@seznam.cz",
  "vladimirkana@seznam.cz",
  "vladimirvecera@email.cz",
  "vlastuse@centrum.cz",
  "vodak@familycoaching.cz",
  "vojtech.ryza@centrum.cz",
  "vojtech.vrana@hella.com",
  "---vojtekj@piar.gtn.sk",
  "---vojtekovcija@mail.t-com.sk",
  "vpazourek@seznam.cz",
  "vrandysek@tiscali.cz",
  "VSAI@seznam.cz",
  "vuk@email.cz",
  "vydlak@agrocs.cz",
  "we.805@bauhaus.cz",
  "XKay@seznam.cz",
  "ymca@setkani.org",
  "Z.Krtek@seznam.cz",
  "zaclonka98@gmail.com",
  "zajicek.honza@seznam.cz",
  "zaoral@zast.cz",
  "zaoralova@zast.cz",
  "zbynek.d@email.cz",
  "zdena@hegrlik.cz",
  "zdena@hegrlik.cz",
  "zdenka.wajdova@centrum.cz",
  "zdislava.nedvedova@post.cz",
  "---zelik@apsoft.sk",
  "zhabr@csas.cz",
  "zlamalo367@seznam.cz",
  "zuzana.kolosova@seznam.cz",
  "zuzana.kostrhonova@seznam.cz",
  "zverinovi@raz-dva.cz"
)
);



?>
