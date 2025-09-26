<?php

# ------------------------------------------------------------------------------------ web prihlaska
# propojení s www.setkani.org - vrátí url článku s popisem akce tzn. s nastaveným id_akce
function web_prihlaska_url($ida) {  trace();
  global $web_setkani;
  $web= explode("//", $web_setkani)[1];
  $a= "nemá článek na $web";
  $uid= select("uid","tx_gncase_part","id_akce='$ida' AND !deleted AND !hidden",'setkani');
  if ($uid) {
    $path= "akce/nove/$uid";
    $a= "popis akce <a href='$web_setkani/$path#anchor$uid' target='web'>$web/$path</a>";
  }
  return $a;
}

# ------------------------------------------------------------------------------------- web zmena_ok
# Vygeneruje QR kód jako PNG pro dané ID akce
//use Endroid\QrCode\Builder\Builder;
//use Endroid\QrCode\Writer\PngWriter;
//function akce_qr($akceId) {
//  // ochrana před nedostupným Endroid\QrCode
//  if (class_exists(Builder::class)) {
//    // Endroid\QrCode existuje
//    global $ezer_version, $abs_root, $rel_root, $ezer_root;
//    $vendor_path= "./ezer$ezer_version/server/vendor";
//    require "$vendor_path/autoload.php";
//    $abs= "$abs_root/docs/$ezer_root/qr-akce-$akceId.png";
//    $rel_qr= "http://$rel_root/docs/$ezer_root/qr-akce-$akceId.png";
//    $base= "$rel_root/prihlaska_2025.php";
//    $url= $base . '?akce=' . urlencode($akceId);
//    $result= Builder::create()
//        ->writer(new PngWriter())
//        ->data($url)
//        ->size(120)
//        ->margin(2)
//        ->build();
//    $png= $result->getString();
//    file_put_contents($abs, $png);
//    return "<a href='$rel_qr' target='QR'><img src='$rel_qr'></a>";
//  }
//  // Endroid\QrCode neexistuje
//  else {
//    return "???";
//  }
//}
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

function akce_qr($akceId) {
  global $ezer_version, $abs_root, $rel_root, $ezer_root;
  $err= $ans= '';
  // Cesty a URL
//  $vendor_path = "./ezer$ezer_version/server/vendor";
  $vendor_path = "$abs_root/db2/vendor";
  $autoload = "$vendor_path/autoload.php";
  $dir_abs = "$abs_root/docs/$ezer_root";
  $file_name = "qr-akce-$akceId.png";
  $abs = "$dir_abs/$file_name";
  // Doporučuju https, pokud je dostupné
  $scheme = 'http';
  $rel_qr = "$scheme://$rel_root/docs/$ezer_root/$file_name";
  $base = "$scheme://$rel_root/prihlaska_2025.php";
  $url = $base . '?akce=' . urlencode($akceId);
  // 1) Composer autoload
  if (!is_readable($autoload)) {
    // Fallback, když není vendor/autoload.php
    $err= "QR knihovna není dostupná (chybí $autoload). <a href=\"$url\" target=\"QR\">Odkaz na přihlášku</a>";
    goto end;
  }
  require_once $autoload;
  // 2) Ověření třídy až po autoloadu
  if (!class_exists(Builder::class)) {
    $err= "QR knihovna není načtena. <a href=\"$url\" target=\"QR\">Odkaz na přihlášku</a>";
    goto end;
  }
  // 3) Ujisti se, že existuje cílový adresář a je zapisovatelný
  if (!is_dir($dir_abs)) {
    if (!@mkdir($dir_abs, 0775, true)) {
      $err= "Nelze vytvořit cílový adresář: $dir_abs";
      goto end;
    }
  }
  if (!is_writable($dir_abs)) {
    // Zvaž nastavení vlastníka/skupiny mimo PHP (chown/chmod)
    $err= "Adresář není zapisovatelný: $dir_abs";
    goto end;
  }
  // 4) Generování a zápis QR s ošetřením chyb
  try {
    $result = Builder::create()
        ->writer(new PngWriter())
        ->data($url)
        ->size(120)
        ->margin(2)
        ->build();
    $png = $result->getString();
    if (file_put_contents($abs, $png) === false) {
      $err= "Nepodařilo se uložit QR do: $abs";
      goto end;
    }
    // 5) Hotovo — zobraz obrázek s odkazem
    $ans= "<a href='$rel_qr' target='QR'><img src='$rel_qr' alt='QR'></a>";
  } catch (\Throwable $e) {
      // Fallback: zobrazit odkaz místo QR
      $err= "Chyba generování QR: " . htmlspecialchars($e->getMessage()) .
             " — <a href=\"$url\" target=\"QR\">Odkaz na přihlášku</a>";
      goto end;
  }
end:
  if ($err) {
    display($err);
    $ans= 'QR není dostupný';
  }
  return $ans;
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
# ------------------------------------------------------------------------------ akce prihlaska_load
# načte data z přihlášek pro cenu podle číselníku verze 2
# pro pobyty nevzniklé online přihláškou doplní defaultní hodnoty 
function akce_prihlaska_load($ida=3094) {
  $verze= select('ma_cenik_verze','akce',"id_duakce=$ida");
  if ($verze!=2) fce_error("akce_prihlaska_load jen pro verzi 2 - $ida má $verze");
  // získání definice přihlášky kvůli stravě
  list($json,$a_od)= select('web_online,datum_od','akce',"id_duakce=$ida");
  $json= str_replace("\n", "\\n", $json);
  $akce= json_decode($json); // definice přihlášky
  $p_od= $akce->p_detska_od??3;
  $p_do= $akce->p_detska_do??12;
  // procházení přihlášek
  $rw= pdo_qry("SELECT id_prihlaska,id_pobyt,id_osoba,id_rodina,nazev,stav,prijata,vars_json,funkce,vzorec
      FROM prihlaska AS w
      LEFT JOIN rodina USING (id_rodina)
      JOIN pobyt USING (id_pobyt)
      WHERE w.id_akce=$ida AND id_pobyt!=0
      -- AND id_rodina=4493 -- Ryzovi
      -- AND id_rodina=2473 -- Palátovi
      ORDER BY id_prihlaska DESC
      -- LIMIT 1");
  while ($rw && (list($idw,$idp,$ido,$idr,$nazev,$stav,$prijata,$json,$funkce,$vzorec)= pdo_fetch_row($rw))) {
    $json= str_replace("\n", "\\n", $json);
    $x= json_decode($json);
    if ($x===null) { fce_error("$idw - $json".json_last_error_msg()); return 'ERROR'; }
    display("$idw pro $nazev - ok");
    foreach ($x->cleni as $ido=>$clen) {
      if (!$clen->spolu) continue;
      list($jmeno,$vek)= select("jmeno,TIMESTAMPDIFF(YEAR,narozeni,'$a_od')",'osoba',"id_osoba=$ido");
      $kn= $vek<3 ? '-' : 'L';
      $porce= $clen->Xporce ?? ($vek<$p_od ? 0 : ($vek<$p_do ? 2 : 1));
      $s= $clen->Xstrava_s??($porce?1:0) ? 'S' : '-';
      $o= $clen->Xstrava_o??($porce?1:0) ? 'O' : '-';
      $v= $clen->Xstrava_v??($porce?1:0) ? 'V' : '-';
      $kp= $porce==1 ? 'C' : ($porce==2  ? 'P' : '-');
//      $kj= ($s?'S':'-').($o?'O':'-').($v?'V':'-');
      $kd= ($clen->Xdieta??1)==1         ? '-' : 'BL';
      $dny= akce_sov2dny("$s$o$v",$ida);
      // pokud není definován pobyt.vzorec=sleva ceny a je to VPS tak dej 1
      if ($funkce==1 && $vzorec==0) {
        query("UPDATE pobyt SET vzorec=1 WHERE id_pobyt=$idp");
      }
      // kat_jidla='$kj', se dopočítává dynamicky
      $qry= "UPDATE spolu SET kat_nocleh='$kn',kat_porce='$kp',kat_dieta='$kd',kat_dny='$dny' "
          . "WHERE id_pobyt=$idp AND id_osoba=$ido /* $jmeno */ ";
      query($qry);
    }
  }
  // doplnění defaultu pro nepřihlášené online
  $rp= pdo_qry("SELECT id_pobyt,id_spolu
      FROM pobyt AS p
      JOIN spolu USING (id_pobyt)
      LEFT JOIN prihlaska AS w USING (id_pobyt)
      WHERE p.id_akce=$ida AND funkce!=99 AND ISNULL(id_prihlaska)");
  while ($rp && (list($idp,$ids)= pdo_fetch_row($rp))) {
    display("bez online pro id_pobyt $idp - ok");
    akce_dny_default($ida,$ids);
  }
}
# ----------------------------------------------------------------------------------- akce prihlaska
# vrátí URL přihlášky pro ostrou nebo testovací databázi
function akce_prihlaska($id_akce,$prihlaska,$par='') {
//  global $answer_db;
//  $prihlaska= 
//      $answer_db=='ezer_db2'      ? 'prihlaska_2025.php' : (
//      $answer_db=='ezer_db2_test' ? 'prihlaska_2025.php'   : '???');
  $goal= "$prihlaska.php?akce=$id_akce$par";
  $url= "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/$goal";
  $res= "<a href='$url' target='pri'>$goal</a>"; 
  return $res;
}
# --------------------------------------------------------------------------------------- prihl show
# vrátí tabulku osobních otázek páru
function prihl_show($idp,$idw) { trace();
  if ($idw) {
    // Přihlášky od roku 2025
    $verze= select1("IFNULL(verze,'')",'prihlaska',"id_prihlaska=$idw");
    switch ($verze) {
      case '2025.2': 
        $html= prihl_show_2025($idp,$idw,2); 
        break;
      case '2025.3': 
        $html= prihl_show_2025($idp,$idw,3); 
        break;
      case '': 
        $html= 'pobyt nevznikl online přihláškou';
        break;
      default: 
        $html= sys_db_rec_show('prihlaska','id_prihlaska',$idw,'-'); 
        break;
    }
  }
  else {
    // Možná přihláška přes cms3
  }
  return $html;
}
function prihl_show_2025($idp,$idpr,$minor) { trace();
# minor je subverze uvedená 
  $nocleh= [1=>'lůžkoviny',2=>'spacák',3=>'bez lůžka',4=>'spí jinde'];
  $html= 'pobyt nevznikl online přihláškou';
//  list($idr,$json)= select('i0_rodina,web_json','pobyt',"id_pobyt=$idp");
  list($idr,$json,$ida,$stav)= select('id_rodina,vars_json,id_akce,stav','prihlaska',
      "id_prihlaska=$idpr");
  if (!$ida) goto end;
//  if (!$json || !$idr) goto end;
  $json= str_replace("\n", "\\n", $json);
  $x= json_decode($json);
//  debug($x);
  $olds= '';
  // dodatky pro vyššší verze než minor=1
  if ($minor >= 1) {
    $m= null;
    display($stav);
    while (preg_match("/(\d)+/", $stav, $m)) {
      debug($m);
      $older= $m[0];
      $olds.= ' < '.tisk2_ukaz_prihlasku($older,$ida,$idp,'','',$older);
      // načteme další
      $stav= select('stav','prihlaska',"id_prihlaska=$older");
    }
  }
  // údaje z verze minor=2,3
  $full= tisk2_ukaz_prihlasku($idpr,$ida,$idp,'','','úplná data');
  $html= "<div style='text-align:right;width:100%'>$full$olds &nbsp; </div>"; 
//  $html= "<div style='text-align:right;width:100%;padding-right:30px'>$full$olds &nbsp; </div>"; 
  $html.= "<div style='font-size:12px'>";
  // strava podle přihlášky
  if (($x->form->strava??0) > 0) {
    // získání definice přihlášky kvůli stravě
    list($json,$a_od)= select('web_online,datum_od','akce',"id_duakce=$ida");
    $json= str_replace("\n", "\\n", $json);
    $akce= json_decode($json); // definice přihlášky
    $p_od= $akce->p_detska_od??3;
    $p_do= $akce->p_detska_do??12;
    // zjisti defaultní porci
    $html.= "<b>Strava</b><ul style='padding-inline-start:25px'>";
    foreach ($x->cleni as $ido=>$clen) {
      if (!$clen->spolu) continue;
      list($jmeno,$vek)= select("jmeno,TIMESTAMPDIFF(YEAR,narozeni,'$a_od')",'osoba',"id_osoba=$ido");
      $porce= $clen->Xporce ?? ($vek<$p_od ? 0 : ($vek<$p_do ? 2 : 1));
      display("$jmeno $vek $p_od $p_do $porce $clen->Xstrava_s");
      $s= $clen->Xstrava_s??($porce?1:0);
      $o= $clen->Xstrava_o??($porce?1:0);
      $v= $clen->Xstrava_v??($porce?1:0);
      $html.= "<li>$jmeno: ";
      if ($s+$o+$v > 0) {
        $html.= "s=$s, o=$o, v= $v";
        $html.= ", porce=".($porce==1 ? 'celá' : ($porce==2 ? 'půl' : 'nic'));
        $html.= ", dieta=".(($clen->Xdieta??1) == 1 ? 'ne' : 'ano');
      }
      else {
        $html.= "bez stravy";
      }
      // nocleh, pokud je definován
      if (isset($akce->p_nocleh)) {
        $html.= ', ubytování '.(($clen->Xnocleh??1) == 1 ? $nocleh[1] : $nocleh[$clen->Xnocleh]);
      }
      $html.= "</li>";
    }
    $html.= "</ul>";
  }
  // osobní pečování podle přihlášky
  if (($x->form->pecouni??0) > 0) {
    $pece= '';
    foreach ($x->cleni as $ido=>$clen) {
      if (!$clen->spolu) continue;
      if (!($clen->o_pecoun??0)) continue;
      $dite= select1('jmeno','osoba',"id_osoba=$ido");
      $pecoun= select1("CONCAT(jmeno,' ',prijmeni)",'osoba',"id_osoba=$clen->o_pecoun");
      $pece.= "<li>o $dite pečuje $pecoun</li>";
    }
    $html.= $pece ? "<b>Osobní pečování</b><ul style='padding-inline-start:25px'>$pece</ul>" : '';
  }
  // žádost o slevu
  $pobyt= $x->pobyt->$idp??0;
  if ($pobyt) {
    if ($pobyt->sleva_zada??0) {
      $html.= "<p><b>Žádá o slevu: </b>".($pobyt->sleva_duvod??'?').'</p>';
    }
    if ($pobyt->pracovni??0) {
      $html.= "<p><b>Vzkaz: </b>".($pobyt->pracovni??'?').'</p>';
    }
    if ($pobyt->Xvps??0) {
      $html.= "<p><b>Služba VPS: </b>".($pobyt->Xvps==1 ? 'ano' : 'odpočinek').'</p>';
    }
  }
  $html.= "</div>";
  // citlivé údaje pro tvorbu skupinek
  if (($x->form->typ??'') == 'M') {
    $html.= "<b>Pro tvorbu skupinek</b>";
    $m= $z= (object)array();
    foreach ($x->cleni as $ido=>$clen) {
      $role= select('role','tvori',"id_rodina=$idr AND id_osoba=$ido");
      if ($role=='a') { $m= $clen; $idm= $ido; }
      if ($role=='b') { $z= $clen; $idz= $ido; }
    }
    if ($idpr) {
      $vars_json= select('vars_json','prihlaska',"id_prihlaska=$idpr");
      $vars_json= str_replace("\n", "\\n", $vars_json);
      $vars= json_decode($vars_json);
      if ($vars===null) {
        $json_error= json_last_error_msg();
        $m_telefon= $z_telefon= $m_email= $z_email= "";
      }
      else {
        $json_error= '';
        $get= function ($fld,$ido) use ($vars) {
          $pair= $vars->cleni->$ido;
          if (isset($pair->$fld)) {
  //          $v= trim(is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld);
            $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? '') : '';
          }
          else $v= false;
          return $v;
        };
        $m_telefon= $get('telefon',$idm); $z_telefon= $get('telefon',$idz);
        $m_email= $get('email',$idm); $z_email= $get('email',$idz);
      }
    }
    $udaje= [
  //    ['- kontakt', $m_kontakt, $z_kontakt],
      ['* email',   $m_email, $z_email],
      ['* telefon', $m_telefon, $z_telefon],
      ['Povaha',    $m->Xpovaha, $z->Xpovaha],
      ['Manželství',$m->Xmanzelstvi, $z->Xmanzelstvi],
      ['Očekávám',  $m->Xocekavani, $z->Xocekavani],
      ['Rozveden',  $m->Xrozveden, $z->Xrozveden],
    ];
    $html.= "<table class='stat' style='font-size:12px;height:50%'>
      <tr><th></th><th width='50%'>Muž</th><th width='50%'>Žena</th></tr>";
    if ($json_error)
      $html.= "<tr><th style='color:red'>JSON</th><td colspan=2 align='center'>$json_error</td></tr>";
    foreach ($udaje as $u) {
      if ($u[1]||$u[2])
        $html.= "<tr><th>$u[0]</th><td>$u[1]</td><td>$u[2]</td></tr>";
    }
    $html.= "</table>";
  }
end:
  return $html;  
}
# --------------------------------------------------------------------------------------- prihl open
# vrátí seznam otevřených přihlášek dané akce
function prihl_open($ida,$hotove=1) { trace();
  $n= $nm= $nma= $nmi= $nx= 0;  // n, n-mobil
  $HAVING= $hotove
      ? "HAVING _naakci!=0"
      : "HAVING _naakci=0";
//      : "HAVING _stavy NOT REGEXP '^ok|,ok|-ok' AND _naakci=0";
  $html= $znami= $novi= '';
  $rp= pdo_qry("SELECT LOWER(p.email) AS _email,IFNULL(GROUP_CONCAT(DISTINCT s.id_pobyt),0) AS _naakci
        ,IFNULL(MAX(id_rodina),0) AS _rodina,IFNULL(GROUP_CONCAT(DISTINCT nazev),'?') AS _nazev
        ,IFNULL(MAX(o.id_osoba),0) AS _osoba,IFNULL(CONCAT(o.prijmeni,' ',o.jmeno),'?')
        ,DATE_FORMAT(MIN(open),'<b>%d.%m</b> %H:%i') AS _open
        ,GROUP_CONCAT(DISTINCT stav ORDER BY p.id_prihlaska) AS _stavy
        ,TRIM(GROUP_CONCAT(DISTINCT LEFT(browser,4) SEPARATOR ' '))
        ,MAX(p.id_prihlaska) AS _id_prihlaska
        ,MAX(IFNULL(p.id_pobyt,0)) AS _pobyt
        ,COUNT(*) AS x, MIN(open) AS _open_
      FROM prihlaska AS p
      LEFT JOIN rodina USING (id_rodina)
      LEFT JOIN osoba AS o ON o.email LIKE CONCAT('%',p.email,'%') 
        AND o.email REGEXP CONCAT('(^|[;,\\\\s]+)',p.email)
      LEFT JOIN pobyt AS pa ON pa.id_akce=$ida 
      LEFT JOIN spolu AS s ON s.id_osoba=o.id_osoba AND pa.id_pobyt=s.id_pobyt
      WHERE p.id_akce=$ida AND p.email!='' -- AND p.email NOT REGEXP '(smidek)'
      -- AND p.id_prihlaska>110
      GROUP BY _email
      $HAVING
      ORDER BY _open_ DESC");
  while ($rp && (list($email,$naakci,$idr,$rodina,$ido,$osoba,$kdy,$stavy,$jak,$idw,$idp)
      = pdo_fetch_array($rp))) {
    $real_idp= select('COUNT(*)','pobyt',"id_pobyt=$idp");
    $_ido= $ido ? tisk2_ukaz_osobu($ido) : '';
    $_idr= $idr ? tisk2_ukaz_rodinu($idr) : '';
    $_idw= $idw ? tisk2_ukaz_prihlasku($idw,$ida,$real_idp,'','',$idw) : $idw;
    display("$real_idp || $hotove==0");
    $skrt= '';
    if (!($real_idp || ($hotove==0 && $idp==0))) {
      $skrt= ' style=text-decoration:line-through';
      $nx++;
    }
    $row= "<tr$skrt><td title='$stavy' align='right'>$_idw => </td><td>$kdy</td><td title='$jak'>$email</td>"
        . "<td>$osoba $_ido</td><td>$rodina $_idr</td>"
        . "<td>$jak</td></tr>";
    if (preg_match("/REG/",$stavy)) $novi.= "\n$row"; else $znami.= "\n$row";
    $n++;
    $nm+= preg_match('/^[AI]/',$jak) ? 1 : 0;
    $nma+= preg_match('/^[A]/',$jak) ? 1 : 0;
    $nmi+= preg_match('/^[I]/',$jak) ? 1 : 0;
  }
  $Jake= $hotove ? "Dokončené" : "Nedokončené";
  $mobilem= round(100*$nm/$n);
  $android= $mobilem ? round(100*$nma/$nm) : 0;
  $iphone= $mobilem ? round(100*$nmi/$nm) : 0;
  $html.= $mobilem ? "<p><i>Celkem $mobilem% mobilem (z toho Android $android%, iPhone $iphone%)</i></p>" : '';
  $html.= "<h3>$Jake přihlášky nově registrovaných</h3><table>$novi</table>";
  $html.= "<h3>$Jake přihlášky známých</h3><table>$znami</table>";
  if ($nx) {
    $html.= "<p><i>Přeškrtnuté řádky signalizují zásahy do evidence z vyšší moci "
        . "- například zrušení pobytu nebo úprava v přihlášce</i></p>";
  }
  return $html;
}
