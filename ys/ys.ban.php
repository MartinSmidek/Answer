<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# ================================================================================================================ BANK
# -------------------------------------------------------------------------------------------------- sql_banksymb
// 10 místný bankovní symbol: odstraň/přidej levostranné nuly
function sql_banksymb ($num,$user2sql=0) {
  if ( $user2sql ) {
    // převeď uživatelskou podobu na sql tvar
    $text= str_pad($num, 10, '0', STR_PAD_LEFT);
  }
  else {
    // převeď sql tvar na uživatelskou podobu (default)
    $text= ltrim($num,'0');
  }
  return $text;
}
# -------------------------------------------------------------------------------------------------- bank_menu_show
function bank_menu_show($k2,$k3) {
  $html= "<div class='CSection CMenu'>";
  switch ( $k2 ) {
  case 'stav': switch ( $k3 ) {
    case 'aktualne':
      $dnes= date('j. n. Y');
      $tab= bank_stavy(&$datum);
      $datum= sql_date($datum);
      $html.= "<h3 class='CTitle'>Aktuální stav bankovních účtů ke dni $datum</h3>";
      $html.= $tab;
      break;
    }
    break;
  case 'loni':
  case 'letos':
    $year= $k2=='letos' ? date('Y') : date('Y')-1;
    switch ( $k3 ) {
    case 'obraty':
      $html.= "<h3 class='CTitle'>Nesouhlasný obrat na výpise a součet převodů v roce $year</h3>";
      $tab= bank_obraty($year);
      $html.= $tab;
      break;
    case 'rady':
      $html.= "<h3 class='CTitle'>Souvislost řad výpisů v roce $year</h3>";
      $tab= bank_rady($year);
      $html.= $tab;
      break;
    }
    break;
  }
  $html.= "</div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- bank_rady
# zjistí úplnost řady výpisů
function bank_rady($year) {
  $result= '';
  // projdeme všechny naše účty
  $html= "";
  $celkem= 0;
  $datum= "0000-00-00";
  $qry= "SELECT * FROM _cis WHERE druh='b_ucty' ORDER BY poradi";
  $res= mysql_qry($qry);
  while ( $res && $a= mysql_fetch_object($res) ) {
    $ucet= $a->hodnota;
    $ucel= $a->ikona;
    $mena= $a->data;
    $u= $a->zkratka;
    $popis= $a->popis;
//                                         if ( substr($u,0,1)!='Y' ) continue;
    // projdeme všechny výpisy daného účtu v daném roce
    $zustatek= false;
    $last= 0;
    $prefix= '';
    $qry1= "SELECT * FROM vypis WHERE ucet='$u' AND year(vypis.datum)=$year ORDER BY ident";
    $res1= mysql_qry($qry1);
    $err= '';
    while ( $res1 && $v= mysql_fetch_object($res1) ) {
      $end= $v->stav;
      $beg= $v->stav_poc;
      $nnn= substr($v->ident,4,3)+0;
      $v_last= $v->ident;
      $last++;
      if ( $zustatek===false ) {
        if ( $nnn!=1 ) {
          $id= substr($v_first,0,4).str_pad($last,3,'0',STR_PAD_LEFT);
          $err.= "chybí první výpis <b>$id</b><br>";
        }
        $v_first= $v->ident;
      }
      else if ( $last!=$nnn ) {
        $id= substr($v_first,0,4).str_pad($last,3,'0',STR_PAD_LEFT);
        $err.= "chybí výpis <b>$id</b><br>";
      }
      else if ( $zustatek!=$beg ) {
        $err.= "výpis {$v->ident} má počáteční zůstatek <b>$beg</b> místo $zustatek<br>";
      }
      $zustatek= $end;
      $last= $nnn;
    }
    $v_last= substr($v_last,4,3);
    $err= $err ? "<span style='color:red'>$err</span>" : 'ok';
    if ( !$last )
      $html.= "<p><b>nejsou výpisy $u</b> $ucet <i>$popis</i></p>$err";
    else
      $html.= "<p><b>$v_first...$v_last</b> $ucet <i>$popis</i></p>$err";
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- bank_obraty
# zjištění výpisů s chybnými převody (součet obratů převodů nedává obrat výpisu)
function bank_obraty($year) {
//                                                 display("bank_obraty($year)");
  // projdeme všechny naše účty
  $nn= 0;
  $html= '';
  $datum= "0000-00-00";
  $qry= "SELECT * FROM _cis WHERE druh='b_ucty' ORDER BY poradi";
  $res= mysql_qry($qry);
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    $ucet= $row['hodnota'];
    $mena= $row['data'];
    $u= $row['zkratka'];
    $popis= $row['popis'];
    $html.= "<h3><b>$u</b> $ucet <i>$popis</i></h3>";
    $n= 0;
    $del= '';
    // kontrola účtu
    $qry1= <<<__QRY
      SELECT datum,soubor,vypis.ident,stav-stav_poc as vobrat,
        sum(if(locate(typ,'56789'),castka,-castka)) as pobrat
      FROM prevod LEFT JOIN vypis ON left(prevod.ident,7)=vypis.ident
      WHERE vypis.ucet='$u' and prevod.ucet='$u' and year(vypis.datum)=$year and stav_poc!=0
      GROUP BY vypis.ident ORDER BY datum DESC
__QRY
    ;
    $res1= mysql_qry($qry1);
    while ( $res1 && $row1= mysql_fetch_assoc($res1) ) {
      $rozdil= $row1['vobrat']-$row1['pobrat'];
      if ( $rozdil!=0 ) {
        $datum= sql_date($row1['datum']);
        $html.= "$del{$row1['soubor']} $datum {$row1['ident']}: $rozdil";
        $n++; $nn++;
        $del= "<br/>";
      }
    }
    $html.= $n ? '' : 'ok';
  }
  $result= $nn ? "celkem $nn inkonzistencí" : '';
  return $result.$html;
}
# -------------------------------------------------------------------------------------------------- bank_stavy
# zjistí aktuální stavy všech účtů
function bank_stavy($datum) {
  $result= '';
  if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string) {
      return @preg_match('/^' . strtr(addcslashes($pattern, '\\.+^$(){}=!<>|')
        , array('*' => '.*', '?' => '.?')) . '$/i', $string);
    }
  }
  // projdeme všechny naše účty
  $result.= "<table>";
  $celkem= 0;
  $datum= "0000-00-00";
  $qry= "SELECT * FROM _cis WHERE druh='b_ucty' ORDER BY poradi";
  $res= mysql_qry($qry);
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    $ucet= $row['hodnota'];
    $ucel= $row['ikona'];
    $zrusen= false;
    $mena= $row['data'];
    if ( $mena[0]=='-' ) {
      $mena= substr($mena,1);
      $zrusen= true;
    }
    $u= $row['zkratka'];
    $popis= $row['popis'];
    $qry1= "SELECT * FROM vypis WHERE ucet='$u' ORDER BY datum DESC LIMIT 1";
    $res1= mysql_qry($qry1);
    if ( $res1 && $row1= mysql_fetch_assoc($res1) ) {
      $datum= max($datum,$row1['datum']);
      $stav= $row1['stav'];
      if ( $mena=='CZK' && !$zrusen ) $celkem+= $stav;
      $stav= $zrusen ? '' : number_format($stav,2,'.',' ');
      $info= $zrusen ? "zrušený účet" : "$mena na účtu";
      $result.= "<tr><td align='right'><b>$stav</b></td><td align='right'>$info</td>"
        . "<td><b>$u</b></td><td>$ucet</td><td>$ucel</td><td><i>$popis</i></td></tr>";
    }
  }
  $result.= "<tr><td align='right'><hr/></td><td></td><td></td></tr>";
  $celkem= number_format($celkem,2,'.',' ');
  $result.= "<tr><td align='right'><b>$celkem</b></td><td>CZK celkem </td><td></td></tr>";
  $result.= "</table>";
  return $result;
}
# -------------------------------------------------------------------------------------------------- bank_sumy
# FORM_MAKE pro vypočet obratu a darů : s.form_make('bank_sumy','load:obrat,dary',p.vypisy.ident.get);
function bank_sumy($ident) {
  global $y;
  $qry= "SELECT sum(if(locate(typ,'56789'),castka,0)) as prijmy,
    sum(if(locate(typ,'1234'),castka,0)) as vydaje, sum(if(locate(typ,'789'),castka,0)) as dary
    FROM prevod WHERE ident LIKE '$ident%'";
  $res= mysql_qry($qry);
  $row= mysql_fetch_assoc($res);
  // formování odpovědi
  $elem= new stdClass;
  $elem->obrat= number_format($row['prijmy']-$row['vydaje'],2,'.',' '); // výdaje jsou záporné
  $elem->dary= number_format($row['dary'],2,'.',' ');
//                                                 display("bank_sumy($ident): {$row['prijmy']}{$row['vydaje']}");
  $y->load= $elem;
  return true;
}
# -------------------------------------------------------------------------------------------------- bank_nalezeno
# FORM_MAKE pro vypočet obratu nalezených převodů :
# n.form_make('bank_nalezeno','load:nalezeno,prijmy,vydaje',p.vypisy.get_query)
function bank_nalezeno($cond) {
  global $y;
  $cond= $cond ? "1 AND $cond" : "1";
  $qry= "SELECT sum(if(locate(typ,'56789'),castka,0)) as prijmy,
    sum(if(locate(typ,'1234'),castka,0)) as vydaje, count(*) as pocet
    FROM prevod WHERE $cond ";
//                                                         display($qry);
  $res= mysql_qry($qry);
  $row= mysql_fetch_assoc($res);
  // formování odpovědi
  $elem= new stdClass;
  $elem->nalezeno= number_format($row['pocet'],0,'',' ');
  $elem->prijmy= number_format($row['prijmy'],2,'.',' ');
  $elem->vydaje= number_format($row['vydaje'],2,'.',' ');
  $y->load= $elem;
  return true;
}
# -------------------------------------------------------------------------------------------------- bank_vypis_icss
# PIPE pro označení výpisů v browse: vyp_n,vyp_r,vyp_p
function bank_vypis_icss($ident) {
  $x= substr($ident,0,1);
  $icss= $x=='N'||$x=='W'||$x=='B' ? 1 : ($x=='R'||$x=='V'||$x=='S' ? 2 : ($x=='P'||$x=='X' ? 3 : 0));
  return $icss;
}
# ================================================================================================== IMPORTY
# importní filtry pro formáty
# Komerční banka:       GPC, KPC
# Volksbanka:           GEM (ACE), KPC
# Raiffeisenbank:       GEM (ACE)
if (!function_exists('fnmatch')) {
  function fnmatch($pattern, $string) {
    return @preg_match('/^' . strtr(addcslashes($pattern, '\\.+^$(){}=!<>|')
      , array('*' => '.*', '?' => '.?')) . '$/i', $string);
  }
}
# -------------------------------------------------------------------------------------------------- bank_import0
# ASK - Zjistit výpisy
# porovná složku $path s tabulkou vypis a vrátí seznam bankovních převodů typu $type (ACE|GEM|GPC)
# vyhovujících masce $patt
# do pole $bank_soubory=>banka vloží pole jmen dosud neimportovaných souborů
function bank_import0($patt='*') {
//                                                 display("banka_import0($patt)");
  global $path_banka, $bank_soubory, $y, $vypisy;
  $result= '';
  $chyby= '';
  $bank_soubory= array();
  if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string) {
      return @preg_match('/^' . strtr(addcslashes($pattern, '\\.+^$(){}=!<>|')
        , array('*' => '.*', '?' => '.?')) . '$/i', $string);
    }
  }
  // projdeme všechny naše účty
  $nase_ucty= array();
  $nase_banky= array();
  $banka_typ= array ( '5500' => 'ACE' );
  $qry= "SELECT * FROM _cis WHERE druh='b_ucty' ";
  $res= mysql_qry($qry);
  $kody6800= '';
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    list($u,$b)= explode('/',$row['hodnota']);
    if ( !in_array($b,$nase_banky) ) $nase_banky[]= $b;
    $nase_ucty[$b][$u]= $row['zkratka'];
    if ( $b==6800) $kody6800.= $row['zkratka'];
  }
                                                debug($nase_ucty,"nase_ucty 6800:$kody6800");
  foreach ($nase_banky as $banka) {
    $bank_soubory[$banka]= array();
    $path= $path_banka[$banka];
    if ( !$path ) {
      fce_error("Soubor config.php neobsahuje cestu pro výpisy banky '$banka'");
      continue;
    }
    $handle= @opendir($path);
    while ($handle && false !== ($file= readdir($handle))) {
      $info= pathinfo($path.$file);
      $typ= strtoupper($info['extension']);
      $soubor= $info['filename'];
                                                display("-- $soubor.$typ");
      if ( $typ==$banka_typ[$banka] && fnmatch($patt,$soubor) ) {
        $year= $banka=='0100' ? substr($soubor,0,4) : '20'.substr($soubor,1,2);
        $qry1= "SELECT * FROM vypis WHERE soubor='$soubor' AND year(datum)='$year'";
        $res1= mysql_qry($qry1);
                                        display("$soubor:".mysql_num_rows($res1));
        $rows1= mysql_num_rows($res1);
        $row1= mysql_fetch_assoc($res1);
        $v_ident= $row1['ident'];
        if ( !$rows1 ) {
          // diskuse správnosti tvaru jména souboru
          switch ($banka) {
          case '0100':
            $ok= preg_match("/^[0-9]{8}$/",$soubor);
            break;
          case '5500':          // název přes eKomunnikator: yynnn_uuuuuuuuu_CZK.ace
            $ok= preg_match("/^[0-9]{5}_[0-9]{9}_CZK$/",$soubor);
            break;
	  case '6800':
            $ok= preg_match("/^[$kody6800][0-9]{5}$/",$soubor);
            break;
          }
          // výstup pro uživatele
          if ( $ok )
            $result.= " $soubor.$typ";
          else
            $chyby.= " <b style='color:red'>$soubor.$typ</b>=chybné jméno";
          $bank_soubory[$banka][]= $soubor;
        }
      }
    }
    if ( $handle ) closedir($handle);
    else fce_error("Cesta '$path' pro výpisy banky '$banka' v config.php není platná");
  }
  $result= $chyby ? "<hr>$chyby<hr>$result" : $result;
  return $result ? $result : "na serveru nejsou přiraveny výpisy k importu";
}
# -------------------------------------------------------------------------------------------------- bank_import1
# zjistí dostupné výpisy a vybere nejstarší dosud nevložený z banky $banka
# pokud je $to_move==1 tak naimportované soubory přesune do $path/yyyy
function bank_import1($bank,$to_move=0) {
                                                display("banka_import1($banka,$to_move)");
  global $bank_soubory;
  $soubor= '';
  bank_import0();
  if ( count($bank_soubory[$bank]) ) {
    sort($bank_soubory[$bank]);
    $soubor= $bank_soubory[$bank][0];
    $one= bank_import($soubor,0,$to_move);
  }
//                                                 debug($bank_soubory,$soubor);
  return $soubor;
}
# -------------------------------------------------------------------------------------------------- bank_imported
# zjistí zda nejstarší výpis v importní složce banky $banka je už vložený
# POZOR 6800 neliší názvy mezi roky
function bank_imported($bank) {
                                                display("banka_imported($banka)");
  global $bank_soubory;
  $imported= '';
  bank_import0();
  if ( count($bank_soubory[$bank]) ) {
    sort($bank_soubory[$bank]);
    $soubor= $bank_soubory[$bank][0];
    $year= $bank='0100' ? substr($soubor,0,4) : date('Y');
    $qry1= "SELECT * FROM vypis WHERE soubor='$soubor' AND year(datum)=$year";
    $res1= mysql_qry($qry1);
    if ( $res1 && mysql_num_rows($res1) ) $imported= $soubor;
  }
//                                                 debug($bank_soubory,$soubor);
  return $imported;
}
# -------------------------------------------------------------------------------------------------- bank_import_remove
# provede smazání vypis a prevod vzniklých z importu daného souboru z daného data
function bank_import_remove($soubor,$datum) {
//                                                 display("bank_import_remove($soubor,$datum)");
  global $path_banka, $y, $vypisy;
  // projdi výpisy
  $p= 0;
  $sql_datum= sql_date($datum,1);
  $qry1= "SELECT * FROM vypis WHERE soubor='$soubor' AND datum='$sql_datum' GROUP BY ident";
  $res1= mysql_qry($qry1);
  while ( $res1 && $row1= mysql_fetch_assoc($res1) ) {
    // odstraň převody
    $v_ident= $row1['ident'];
    $qry2= "DELETE FROM prevod WHERE left(prevod.ident,7)='$v_ident' ";
    $res2= mysql_qry($qry2);
    $p+= mysql_affected_rows();
  }
  // odstraň výpisy
  $qry3= "DELETE FROM vypis WHERE soubor='$soubor' AND datum='$sql_datum'";
  $res3= mysql_qry($qry3);
  $v= mysql_affected_rows();
  // redakce zprávy
  $text.= "$soubor z $datum: odstraněno $v výpisů a $p převodů";
  return $text;
}
# -------------------------------------------------------------------------------------------------- bank_import
# ASK - Přidej výpisy
# porovná složku $path s tabulkou vypis a provede import bankovních převodů typu $type (ACE|GEM|GPC)
# vyhovujících masce $patt
# pokud je $reimport==1 provede smazání položek v vypis a prevod a potom provede import
# pokud je $to_move==1 tak naimportované soubory přesune do $path/yyyy
# change= 'vypisy'|'prevody' určuje (pokud je reimport) zda se mení jen výpis nebo i jeho převody
function bank_import($patt='*',$reimport=0,$to_move=0,$change='vypisy') {
                                                display("banka_import($patt,$reimport,$to_move,$change)");
  global $path_banka, $y, $vypisy;
  $kontrola= $change=='missing';
  $msg= '';
  $n_vypisu= 0;
  $ids_vypisu= '';
  if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string) {
      return @preg_match('/^' . strtr(addcslashes($pattern, '\\.+^$(){}=!<>|')
        , array('*' => '.*', '?' => '.?')) . '$/i', $string);
    }
  }
  // projdeme všechny naše účty
  $nase_ucty= array();
  $nase_banky= array();
  $ucel_uctu= array();                  // obsah _cis.ikona: platby|dary,platby
  $banka_typ= array ( '5500' => 'ACE');
  $qry= "SELECT * FROM _cis WHERE druh='b_ucty' ";
  $res= mysql_qry($qry);
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    list($u,$b)= explode('/',$row['hodnota']);
    if ( !in_array($b,$nase_banky) ) $nase_banky[]= $b;
    $nase_ucty[$b][$u]= $row['zkratka'];
    $ucel_uctu[$row['zkratka']]= $row['ikona'];
  }
//                                         debug($nase_banky,'$nase_banky');
//                                         debug($nase_ucty,'$nase_ucty');
//                                         debug($ucel_uctu,'$ucel_uctu');
//                                         debug($path_banka,'$path_banka');
  foreach ($nase_banky as $banka) {
    $path= $path_banka[$banka];
    if ( !$path ) {
      fce_error("Soubor config.php neobsahuje cestu pro výpisy banky '$banka'");
      continue;
    }
    $handle= @opendir($path);
    while ($handle && false !== ($file= readdir($handle))) {
      $info= pathinfo($path.$file);
      $typ= strtoupper($info['extension']);
      $soubor= $info['filename'];
      $vypisy= array();
      if ( $typ==$banka_typ[$banka] && fnmatch($patt,$soubor) ) {
                                        display("$soubor: reimport=$reimport, fnmatch($patt,$soubor)=".fnmatch($patt,$soubor));
//         $year= $banka=='0100' ? substr($soubor,0,4) : date('Y');
        $year= $banka=='0100' ? substr($soubor,0,4)
          : ((substr($soubor,1,1)=="0"||substr($soubor,1,1)=="1") ? "20" : "19").substr($soubor,1,2);
        $qry1= "SELECT * FROM vypis WHERE soubor='$soubor' AND year(datum)=$year";
        $res1= mysql_qry($qry1);
        $rows1= $res1 ? mysql_num_rows($res1) : 0;
        if ( $rows1==0 || $reimport ) {
                                        display("$soubor/$rows1: prošlo testem");
          $row1= mysql_fetch_assoc($res1);
          $v_ident= $row1['ident'];
          if ( $rows1 && !$kontrola ) {
            // pokud je soubor v tabulkách vypis a prevod, vymažeme jej
            if ( $reimport==0 || $change=='prevody' ) {
              // ale převody jen pokud se to chce
              $qry3= "DELETE FROM prevod USING prevod,vypis WHERE soubor='$soubor' AND left(prevod.ident,7)=vypis.ident ";
              $res3= mysql_qry($qry3);
            }
            $qry2= "DELETE FROM vypis WHERE soubor='$soubor' ";
            $res2= mysql_qry($qry2);
          }
          // provedeme vlastní import do polí vypisy, prevody
          switch ( $typ ) {
          case 'ACE':                                   // 6800 old
//             $buf= file_get_contents($path.$file);
//             bank_ace($buf,$soubor,&$yyyy);
//             break;
//           case 'GEM':                                   // 6800
            $buf= file_get_contents($path.$file);
            bank_gem_ext($path.$file,$soubor,&$yyyy);
            break;
          case 'GPC':                                   // 0100
            bank_gpc($path.$file,$soubor,&$yyyy);
            break;
          }
                                        debug($vypisy,'$vypisy',(object)array('win1250'=>1));
// 					return 'test';

          // provedeme záměny čísel účtů symboly a vyrobíme ident
          foreach ( $vypisy as $v => $vypis ) {
            $ucet= $nase_ucty[$banka][$vypis['ucet']];
                                        debug($nase_ucty,'$nase_ucty');
            if ( !$ucet ) return fce_error("Účet '{$vypis['ucet']}' není zapsán v číselníku vlastních účtů banky $banka");
            if ( $typ=='GEM' ) {
              // pro formát GEM provedeme kontrolu konzistence jména souboru a obsahu
              if ( $soubor[0]!=$ucet )
                return fce_error("soubor $soubor obsahuje výpis účtu {$vypis['ucet']} - to je špatně vytvořené jméno");
              if ( substr($soubor,3,3)!=$vypis['vypis'] )
                return fce_error("soubor $soubor obsahuje výpis číslo {$vypis['vypis']} - to je špatně vytvořené jméno");
            }
            $vypisy[$v]['ucet']= $ucet;
            $v_ident= $ucet.substr($vypis['datum'],2,2)."_".$vypis['vypis'];
            $vypisy[$v]['ident']= $v_ident;
            // doplň údaje do převodů
            if ( $vypis['prevody'] ) foreach ( $vypis['prevody'] as $p => $prevod ) {
              $vypisy[$v]['prevody'][$p]['ucet']= $ucet;
              $vypisy[$v]['prevody'][$p]['ident']= $v_ident.$prevod['ident'];
              // přesuny mezi vlastními účty
              if ( $nase_ucty[$prevod['banka']][$prevod['protiucet']] ) {
                $vypisy[$v]['prevody'][$p]['kat']= "#u";
              }

/* automatika

              // příjem ze sbírkového účtu B je dar
              else if ( $prevod['typ']==5 && $ucet=='B' ) {
                $vypisy[$v]['prevody'][$p]['typ']= 7;
                $vypisy[$v]['prevody'][$p]['kat']= "+d";
              }
              // příjem s ksym=0998 je dar - soupis složenek
              else if ( $prevod['typ']==5 && $prevod['ksym']=='0998' ) {
                $vypisy[$v]['prevody'][$p]['typ']= 8;
                $vypisy[$v]['prevody'][$p]['kat']= "+d";
              }
              // může být příjem darem?
              else if ( $prevod['typ']==5 ) {
//                                                 display("{$vypisy[$v]['prevody'][$p]['ident']} ... ");
                $clen= 0;
                if ( !$clen ) {
                  // poznáme člena z VSYM?
                  $clen= bank_vsym2clen($prevod['vsym'],$metoda);
                  if ( !$clen )
                    // poznáme člena z POPISu účtu (rozkladem na příjmení a jméno a s požadavkem jednznačnosti
                    $clen= bank_popis2clen($prevod['popis'],$metoda);
//                                                   display_(" B $metoda");
                }
                if ( !$clen && $prevod['typ']==5 && $prevod['protiucet']!='000000-0000000000' ) {
//                                                 display_("$clen {$prevod['typ']} {$prevod['protiucet']} ");
                  // pokud je účet nenulový - přišel z něj někdy dar, který jsme určili?
                  $cond= "protiucet='{$prevod['protiucet']}' AND banka='{$prevod['banka']}' AND clen!=0";
                  $qry4= "SELECT clen FROM prevod WHERE $cond ORDER BY splatnost DESC LIMIT 1";
                  $res4= mysql_qry($qry4);
                  $rows4= mysql_num_rows($res4);
                  if ( $rows4 ) {
                    $row4= mysql_fetch_assoc($res4);
                    $clen= $row4['clen'];
//                                                   display_(" A $clen je člen podle čísla účtu");
                  }
                }
                if ( $clen ) {
                  $vypisy[$v]['prevody'][$p]['clen']= $clen;
                  if ( strpos($ucel_uctu[$ucet],'dary')!==false ) {
                    // pokud účet připouští dary
                    $vypisy[$v]['prevody'][$p]['typ']= 7;
                    $vypisy[$v]['prevody'][$p]['kat']= "+d";
                  }
                  elseif ( strpos($ucel_uctu[$ucet],'platby')!==false ) {
                    // jinak, pokud jsou možné platby
                    $vypisy[$v]['prevody'][$p]['kat']= "+o";
                  }
                }
              }
              // zjistíme kat
//             $vypisy[$v]['prevody'][$p]['kat']= "+-#";

*/

            }

          }
//                                         debug($vypisy,"vypisy $soubor");
          // zkontrolujeme obrat
          foreach ( $vypisy as $vypis ) {
            $obrat1= $vypis['stav'] - $vypis['stav_poc'];
            $obrat2= $vypis['*obrat'];
            $rozdil= round($obrat1-$obrat2,2);
                                        display("$obrat%$obrat2%$rozdil v ".count($vypis['prevody']));
            if ( abs($rozdil)>0.01 ) {
              $vypis['_error']= "inkonsistence obratu: $obrat1 x $obrat2";
                                        debug($vypis,"vypis ze $soubor");
              fce_error("Při importu $soubor byla zjištěna inkonsistence obratu: $obrat1 x $obrat2");
            }
          }
          // zapíšeme do tabulek
          foreach ( $vypisy as $vypis ) if ( !$vypis['_error'] ) {
            if ( $kontrola && $change=='missing' ) {
              // kontrola položek výpisu a jeho převodů a případné doplnění převodu
              $qry= bank_check_qry('vypis','ident,vypis,ucet,datum,soubor,stav_poc,stav',$vypis);
//               $qry= bank_check_qry('vypis','ident,vypis,ucet,datum,soubor,stav',$vypis);
//                                         display("qry:$qry");
              $res= mysql_qry($qry);
              if ( !$res ) fce_error("banka_import: kontrola výpisu {$vypis['ident']} selhala");
              if ( $res && $row= mysql_fetch_assoc($res) ) {
                if ( $er= $row['err_list'] ) {
                  fce_error("Při kontrole $soubor byla zjištěna změna položek vypisu: $er");
                }
                else {
                  // vypis je stejný jako byl
                  if ( $vypis['prevody'] ) foreach ( $vypis['prevody'] as $prevod ) {
                    $qry= bank_check_qry('prevod'
                      ,'ident,vypis,ucet,popis,clen,typ,castka,protiucet,banka,splatnost,ksym,vsym,ssym,kat,poznamka'
                      ,$prevod,'typ,kat,clen,dar');
//                                         display("qry:$qry");
                    $res= mysql_qry($qry);
                    $rows= mysql_num_rows($res);
                    if ( $rows ) {
                      if ( $res && $row= mysql_fetch_assoc($res) ) {
                        if ( $er= $row['err_list'] ) {
                          fce_error("Při kontrole {$prevod['ident']} byla zjištěna změna položek převodu: $er");
                        }
                      }
                    }
                    else {
                      // zjistíme klíč výpisu
                      $res= mysql_qry("SELECT id_vypis FROM vypisy WHERE ident={$vypis['ident']}");
                      $row= mysql_fetch_assoc($res);
                      $id_vypis= $row['id_vypis'];
                      $prevod['id_vypis']= $id_vypis;
                      // vložíme převody
                      $qry= bank_insert_qry('prevod'
                        ,'id_vypis,ident,vypis,ucet,popis,clen,typ,castka,protiucet,banka,splatnost,ksym,vsym,ssym,kat,poznamka',
                        $prevod);
//                                         display("qry:$qry");
                      $res= mysql_qry($qry);
                      if ( !$res ) fce_error("banka_import: přidání převodu {$prevod['ident']} selhalo");
                      else {
                        $n_vypisu++;
                        $ids_vypisu.= "{$prevod['ident']} ";
                      }
                    }
                  }
                }
              }
            }
            else {
              // doplnění nového výpisu nebo oprava starého znovunačtením
              $n_vypisu++;
              $ids_vypisu.= " {$vypis['ident']} ";
              $qry= bank_insert_qry('vypis','ident,vypis,ucet,datum,soubor,stav_poc,stav',$vypis);
              $res= mysql_qry($qry);
              $id_vypis= mysql_insert_id();
              if ( !$res ) fce_error("banka_ace: zápis výpisu {$vypis['ident']} selhal");
              if ( $reimport==0 || $change=='prevody' ) {
                if ( $vypis['prevody'] ) foreach ( $vypis['prevody'] as $prevod ) {
                  $prevod['id_vypis']= $id_vypis;
                  $qry= bank_insert_qry('prevod'
                    ,'id_vypis,ident,vypis,ucet,popis,clen,typ,castka,protiucet,banka,splatnost,ksym,vsym,ssym,kat,poznamka',
                    $prevod);
                  $msg.= "{$prevod['ident']} ";
// 							display("QRY:$qry");
                  $res= mysql_qry($qry);
                  if ( !$res ) fce_error("banka_import: zápis převodu {$prevod['ident']} selhal");
                }
              }
            }
          }
          // přesuneme soubor do podsložky s názvem roku
          if ( $to_move && $yyyy && !$kontrola ) {
            if ( !is_dir($path.$yyyy) ) mkdir($path.$yyyy);
            $file1= $path.$yyyy.'/'.$file;
            if ( file_exists($file1) && $to_move )
              fce_error("Soubor $file nelze odstranit z FTP složky banky $banka");
            else
              rename($path.$file,$file1);
          }
        }
      }
    }
    if ( $handle ) closedir($handle);
    else fce_error("Cesta '$path' pro výpisy banky '$banka' v config.php není platná");
  }
//                                         debug($vypisy,'vypisy 2');
  if ( $n_vypisu ) $n_vypisu.=" (".trim($ids_vypisu).")";
  return $n_vypisu; //$msg;
}
# -------------------------------------------------------------------------------------------------- bank_popis2clen
# zkusíme z popisu účtu uhodnout člena
# předpokládáme, že popis= příjmení jméno ...
function bank_popis2clen($popis,&$metoda) {
  $clen= 0;
  $metoda= '';
  list($prijmeni,$jmeno)= explode(' ',trim($popis));
  if ( $prijmeni && $jmeno ) {
    $qry= "SELECT id_clen FROM clen WHERE prijmeni LIKE '$prijmeni' COLLATE utf8_general_ci"
      . " AND jmeno LIKE '$jmeno' COLLATE utf8_general_ci AND left(deleted,1)='' ";
    $res= mysql_qry($qry);
    if ( $res && mysql_num_rows($res)==1 ) {
      $row= mysql_fetch_assoc($res);
      $clen= $row['id_clen'];
    }
  }
  return $clen;
}
# -------------------------------------------------------------------------------------------------- bank_vsym2clen
# zkusíme z variabilního symbolu uhodnout člena
# predanych 10 cislic je bud clenske nebo rodne cislo nebo nic
#   0000nnnnnn - členské číslo, je-li n>12
#   rrmmddxxxx - rodné číslo
#   9999nnnnnn - hromadný dar
function bank_vsym2clen($vsym,&$metoda) {
  $clen= 0;
  $metoda= '';
  if ( verify_rodcis($vsym) ) {
    $metoda= "$vsym je rc";
    // rodne cislo - rrmmddxxx(x)
    $qry= "SELECT id_clen FROM clen WHERE rodcis='$vsym' AND left(deleted,1)='' LIMIT 1";
    $res= mysql_qry($qry);
    if ( $res && $row= mysql_fetch_assoc($res) ) {
      // rodné číslo existuje Klubu?
      $clen= $row['id_clen'];
      $metoda.= ", v Klubu je";
    }
    else
      $metoda.= ", v Klubu není";
  }
  else {
    if ( substr($vsym,0,4)=="9999" ) {
      // hromadny dar=9999nnnnn
      $clen= intval(substr($vsym,5));
      $metoda.= "$vsym určuje hromadný dar člena $clen";
    }
    else {
      $clen= intval($vsym);
      if ( $clen>12 && $clen<999999 ) {
        // členské číslo=0000nnnnnn
        $metoda.= "$vsym může být členské číslo $clen";
      }
      else $clen= 0;
    }
    if ( $clen ) {
      // existuje číslo v Klubu?
      $qry= "SELECT id_clen FROM clen WHERE id_clen=$clen AND left(deleted,1)='' LIMIT 1";
      $res= mysql_qry($qry);
      if ( mysql_num_rows($res) ) {
        $metoda.= ", v Klubu je";
      }
      else {
        $metoda.= ", v Klubu není";
        $clen= 0;
      }
    }
  }
  if ( NOE && !$clen && $vsym>100000 ) {
    // i chybné formáty rodného čísla
    $qry= "SELECT id_clen FROM clen WHERE rodcis=$vsym AND left(deleted,1)='' LIMIT 1";
    $res= mysql_qry($qry);
    if ( $res && $row= mysql_fetch_assoc($res) ) {
      // rodné číslo existuje Klubu?
      $clen= $row['id_clen'];
      $metoda.= ", v Klubu je 2";
    }
    else
      $metoda.= ", v Klubu není 2, protože $qry";
  }
//                                         display("bank_vsym2clen($vsym) - $clen $metoda");
  return $clen;
}
# -------------------------------------------------------------------------------------------------- bank_check_qry
# zjistí shodu všech položek pro "ident={$values['ident']}"
function bank_check_qry($table,$items,$values,$buts='') {
  $cond= "ident='{$values['ident']}'";
  $del= '';
  $list= '';
  $but= explode(',',$buts);
  foreach (explode(',',$items) as $item) {
    if ( !in_array($item,$but) ) {
      $value= mysql_real_escape_string($values[$item]);
      $list.= "{$del}if($item='$value','',concat('$item:','/','$value,$item'))";
      $del= ',';
    }
  }
  $qry= "SELECT CONCAT($list) as err_list FROM $table WHERE $cond";
  return $qry;
}
# -------------------------------------------------------------------------------------------------- bank_insert_qry
function bank_insert_qry($table,$items,$values) {
  $qry= "INSERT INTO $table ($items) VALUES (";
  $del= '';
  foreach (explode(',',$items) as $item) {
    $value= mysql_real_escape_string($values[$item]);
    $qry.= "$del'$value'";
    $del= ',';
  }
  return "$qry);";
}
# -------------------------------------------------------------------------------------------------- bank_ace_kod
# překóduje řetězec do UTF-8
function bank_ace_kod($val) {
  $val= iconv("CP852","UTF-8",$val);
  return $val;
}
# -------------------------------------------------------------------------------------------------- bank_gem_kod
# překóduje řetězec do UTF-8
function bank_gem_kod($val) {
  $val= iconv("CP1250","UTF-8",$val);
  return $val;
}
# -------------------------------------------------------------------------------------------------- bank_gem_ext
# rozkóduje text se strukturou z Reiffensenbank GEMINI-EXT
function bank_gem_ext($gpc,$soubor,&$yyyy) {
  global $y, $vypisy;
                                                display("<b>bank_gem_ext($soubor)</b>");
  $yyyy= "20".substr($soubor,0,2);
  $msg= '';
  $f= fopen($gpc, "r");
  $vypis= array();
  $nprikaz= 0;
  $b= fgets($f);
						display(win2utf($b,true));
  while ( !feof($f) ) {
    $b= fgets($f,4096);
    if ( strlen($b) ) {
      // zjištění a kontrola informací o výpise
      $nprikaz++;
      $nvypis= str_pad(trim(substr($b,38,5),' 0'),3,'0',STR_PAD_LEFT);
      $smer= trim(substr($b,72,1));
      $castka= trim(substr($b,74,15));
      $sign_castka= $castka*($smer=='D' ? -1 : 1);
      if ( !$vypis['vypis'] ) {
        $vypis['ucet']= substr($b,0,6).'-'.substr($b,6,10);
        $vypis['soubor']= $soubor;
        $vypis['vypis']= $nvypis;
        $vypis['datum']= substr($b,48,4).'-'.substr($b,52,2).'-'.substr($b,54,2);
        $stav= trim(substr($b,89,15));
        $vypis['stav_poc']= $stav - $sign_castka;
//                                                         display("$stav - $castka = {$vypis['stav_poc']}");
        $vypis['*obrat']+= 0;
      }
      else if ( $vypis['vypis']!=$nvypis )
        return fce_error("bank_ace: v jednom souboru smí být jen jeden výpis, je tam $nvypis a {$vypis['vypis']}");
      // zpracování dat převodu
      $prevod= array();
      $prevod['ident']= str_pad($nprikaz,3,'0',STR_PAD_LEFT);
      $prevod['splatnost']= substr($b,64,4).'-'.substr($b,68,2).'-'.substr($b,70,2);
      $prevod['typ']= $smer=='C' ? 5 : 1;
      $prevod['castka']= $castka;
      $vypis['*obrat']+= $sign_castka;
      $prevod['vypis']= $nvypis;
      $posting= trim(substr($b,125,16));
      $info= substr($b,175);
      // obsah proměnných polí
      $infoc= preg_match_all("/[\4]?([^\4]*)/",$info,$aa);
      $a= $aa[1];
//                                                           debug($a,$posting);
      switch ( $posting ) {
      case 'lib.':              // nepopsáno - úrok
        $prevod['popis']= bank_gem_kod(substr($b,141));
        if ( $smer=='C' ) $prevod['typ']= 6;
//                                                           debug($a,$posting);
        break;
      case 'Z8-GEFE':           // nepopsáno - poplatek
      case 'GE-IC':             // Úrok / Poplatek
      case 'PK-ACTR':           // Transakce přes platební karty
        $prevod['popis']= bank_gem_kod($info);
        $prevod['poznamka']= '';
        if ( $smer=='C' && $posting=='GE-IC' ) $prevod['typ']= 6;
  //                                                 debug($prevod,'úrok');
        break;
      case 'GE-FT':             // Zahraniční transakce
        # Příklad výpisu: 12.11.08 Reference: P0811120001OP08  5,613.51
        #                 Částka platby: 159.60 EUR Kurz: 25.77389500 Poplatky: 1,050.00 CZK
      case 'GE-TT':             // Transakce na přepážce -- bývá bohužel označena i jako GE-FT (V09028)
        if ( count($a)>15 ) {
          // Zahraniční transakce
          $prevod['popis']= "zahraniční {$a[0]}";
          $prevod['poznamka']= "poplatky:{$a[15]} částka:{$a[4]}{$a[3]} kurz:{$a[2]}";
        }
        else {
          // Transakce na přepážce
          $prevod['popis']= bank_gem_kod(trim(substr($b,141,34)));
        }
        break;
      case 'FOR_CC':            // Příchozí zahraniční platba - inkaso šeku
        $prevod['popis']= "inkaso zahr. šeku {$a[0]}";
        $poplatek= round($a[4]*$a[2] - $prevod['castka'],2);
        $prevod['poznamka']= "poplatky:$poplatek částka:{$a[4]}{$a[3]} kurz:{$a[2]}";
                                                          debug($prevod,$posting);
        break;
      case 'I-GE-CC':           // Clearingové (domácí) transakce – příchozí
      case 'O-GE-CC':           // Clearingové (domácí) transakce – odchozí
        $prevod['ksym']= str_pad($a[5],4,"0",STR_PAD_LEFT);
        $poznamka= trim($a[16]) ? bank_gem_kod("B: {$a[16]}") : '';
        $poznamka.= trim($a[12]) ? bank_gem_kod(" D: {$a[12]}") : '';
        $poznamka.= trim($a[13]) ? bank_gem_kod(" K: {$a[13]}") : '';
        $poznamka= str_replace('        ',' ',$poznamka);
        $poznamka= str_replace('    ',' ',$poznamka);
        $prevod['poznamka']= str_replace('  ',' ',$poznamka);
        if ( $posting[0]=='I' ) {         // kreditní platby
          $protiucet= $a[6];
          $prevod['vsym']= str_pad($a[14],10,"0",STR_PAD_LEFT);
          $prevod['ssym']= str_pad($a[10],10,"0",STR_PAD_LEFT);
          $prevod['banka']= $a[2];
          $prevod['popis']= bank_gem_kod($a[7]);
        }
        else {                            // debetní platby
          $protiucet= $a[8];
          $prevod['vsym']= str_pad($a[15],10,"0",STR_PAD_LEFT);
          $prevod['ssym']= str_pad($a[11],10,"0",STR_PAD_LEFT);
          $prevod['banka']= $a[3];
          $prevod['popis']= bank_gem_kod($a[9]);
        }
        $protiucet= str_pad($protiucet,16,'0',STR_PAD_LEFT);
        $protiucet= substr($protiucet,0,6).'-'.substr($protiucet,6,10);
        $prevod['protiucet']= $protiucet;
        break;
      default:
                                            fce_error("$soubor/$nvypis/$nprikaz :61: $posting -- neošetřeno A");
        break;
      }
      $vypis['prevody'][$nprikaz]= $prevod;
      $vypis['stav']= $vypis['stav_poc'] + $vypis['*obrat'];

  //     if ( $nprikaz==2 ) break;
    }
  }
//                                                           debug($vypis,$soubor);
  fclose($f);
  if ( count($vypis) ) $vypisy[]= $vypis;
  return $msg;
}
# -------------------------------------------------------------------------------------------------- bank_ace
# rozkóduje text se strukturou MT940
function bank_ace($buf,$soubor,&$yyyy) {
  global $y, $vypisy;
                                                display("<b>bank_ace($soubor)</b>");
  $msg= '';
//   $mc= preg_match_all("/:(86):((?:.*\r\n){2})|:([\dF]+):([^:]*)/",$buf,$m);
  $mc= preg_match_all("/:(86):([IO](?:\4[^\4]*){16})|:([\dF]+):([^:]*)/",$buf,$m);
                                                        debug($m);
  $vypis= array();
  for ($i= 0; $i<$mc; $i++) {
    $kod= $m[1][$i] ? $m[1][$i] : $m[3][$i];
    $x= $m[1][$i] ? $m[2][$i] : $m[4][$i] ;
    $x= str_replace("\r\n","",$x);
                                                display("položka kod=$kod");
    switch ( $kod ) {
    // VYPIS I.
    case '20':	// -- referenční číslo transakce datum+účet
      break;
    case '25':	// identifikace účtu
      $vypis= array();
      $vypis['ucet']= $ucet= '000000-'.substr($x,0,10);
//                                                 display("25 účet:$ucet");
      break;
    case '28':	// číslo výpisu
      $vypis['soubor']= $soubor;
      $vypis['vypis']= $nvypis= substr($x,0,3);
      $prevod= array();
      $nprikaz= 0;
      $vypis['*obrat']= 0;
      break;
    case '60F':	// výchozí (opening) balance
      $vyp_cd= substr($x,0,1);
      $yy= substr($x,1,2);
      $vypis['datum']= "20$yy-".substr($x,3,2)."-".substr($x,5,2);
      $yyyy= "20$yy";
      $balance= substr($x,10);
      $vypis['stav_poc']= $stav= str_replace(',','.',$balance) * ($vyp_cd=='D' ? -1 : 1);
//                                                 display("60F (opening) $stav $x");
      $prevod= array();
      break;
    // PŘEVOD
    case '61':	// detail  o transakci
      $nprikaz++;
      $c61= preg_match("/(\d+)([CD])([\d,]+)(NCHK|NMSC)([^\/]+|NONREF)(?:\/\/)(.{7})(.+)/",$x,$i61);
      list($filler,$datum,$cd,$castka,$filler,$filler,$posting,$info)= $i61;
      $posting= trim($posting);
      $prevod['splatnost']= "20".substr($datum,0,2)."-".substr($datum,2,2)."-".substr($datum,4,2);
      $prevod['castka']= str_replace(',','.',$castka) ; // * ($cd=='D' ? -1 : 1);
      $prevod['ucet']= $ucet;
      $prevod['typ']= $cd=='D' ? 1 : 5;
      $prevod['vypis']= $nvypis;
      $prevod['ident']= str_pad($nprikaz,3,'0',STR_PAD_LEFT);
      switch ( $posting ) {
      case 'Z8-GEFE':           // nepopsáno - poplatek
      case 'GE-IC':             //  Úrok / Poplatek
      case 'PK-ACTR':           // Transakce přes platební karty
        // k tomuto typu nebude :86:
        $vypis['*obrat']+= ($cd=='D' ? -1 : 1) * $prevod['castka'];
        $prevod['popis']= bank_ace_kod($info);
        $prevod['poznamka']= '';
        $vypis['prevody'][$nprikaz]= $prevod;
        if ( $cd=='C' && $posting=='GE-IC' ) $prevod['typ']= 6;
//                                                 debug($prevod,'úrok');
        $prevod= array();
        break;
      case 'GE-TT':             // Transakce na přepážce
        $c61a= preg_match("/(.{10})(.+)/",$info,$i61i);
//                                             display("GE-TT $nprikaz:61:$x"); // debug($i61); debug($i61i);
        list($filler,$info1,$info2)= $i61i;
        $vypis['*obrat']+= ($cd=='D' ? -1 : 1) * $prevod['castka'];
        $prevod['popis']= bank_ace_kod($info2);
        $prevod['poznamka']= "přepážka $info1";
        // k tomuto typu nebude :86:
        $vypis['prevody'][$nprikaz]= $prevod;
        $prevod= array();
        break;
      case 'GE-FT':             // Zahraniční transakce
      case 'I-GE-CC':           // Clearingové (domácí) transakce – příchozí
      case 'O-GE-CC':           // Clearingové (domácí) transakce – odchozí
        // musí následovat :68:
//                                             display("GE-FT $nprikaz:61:$x"); debug($i61); debug($i61i); debug($prevod);
        break;
      default:
                                            fce_error("$soubor/$nvypis/$nprikaz :61: $posting -- neošetřeno B");
        break;
      }
      break;
    case '86':	// informace o platbě -v případě že
      $infoc= preg_match_all("/[\4]?([^\4]*)/",$x,$info);
//                                                 display_(" . ");
//                                                         debug($info[1]);
      if ( $posting=='GE-FT' ) {          // původ postingu = “GE-FT“ detaily zahraniční platby;
        # Příklad výpisu: 12.11.08 Reference: P0811120001OP08  5,613.51
        #                 Částka platby: 159.60 EUR Kurz: 25.77389500 Poplatky: 1,050.00 CZK
        $prevod['popis']= "zahraniční {$info[1][0]}";
        $prevod['poznamka']= "částka:{$info[1][4]}{$info[1][3]} kurz:{$info[1][2]} poplatky:{$info[1][15]}";
      }
      else {                                    // původ postingu  = “O-GE-CC“ nebo „I-GE-CC“ detaily domácí platby
        $prevod['ksym']= str_pad($info[1][5],4,"0",STR_PAD_LEFT);
        $prevod['ssym']= str_pad($info[1][10],10,"0",STR_PAD_LEFT);
        $prevod['vsym']= str_pad($info[1][14],10,"0",STR_PAD_LEFT);
        $poznamka= $info[1][16] ? bank_ace_kod("B: {$info[1][16]}") : '';
        $poznamka.= $info[1][12] ? bank_ace_kod(" D: {$info[1][12]}") : '';
        $poznamka.= $info[1][13] ? bank_ace_kod(" K: {$info[1][13]}") : '';
  //       $poznamka= bank_ace_kod("D: {$info[1][12]} K:{$info[1][13]} B:{$info[1][16]}");
        $poznamka= str_replace('        ',' ',$poznamka);
        $poznamka= str_replace('    ',' ',$poznamka);
        $prevod['poznamka']= str_replace('  ',' ',$poznamka);
        if ( $info[1][0]=='I' ) {         // příchozí platby
          $protiucet= $info[1][6];
          $prevod['banka']= $info[1][2];
          $prevod['popis']= bank_ace_kod($info[1][7]);
          $prevod['typ']= 5;
        }
        else {                            // odchozí platby
          $protiucet= $info[1][8];
          $prevod['banka']= $info[1][3];
          $prevod['popis']= bank_ace_kod($info[1][9]);
          $prevod['typ']= 1;
        }
        $protiucet= str_pad($protiucet,16,'0',STR_PAD_LEFT);
        $protiucet= substr($protiucet,0,6).'-'.substr($protiucet,6,10);
        $prevod['protiucet']= $protiucet;
      }
      $vypis['*obrat']+= ($cd=='D' ? -1 : 1) * $prevod['castka'];
//                       if ( $posting=='GE-FT' ){           /*display("$nprikaz:86:$x"); debug($info);*/  debug($vypis); debug($prevod);  }
      $vypis['prevody'][$nprikaz]= $prevod;
      $prevod= array();
      break ;
    // VYPIS II.
    case '62F':	// konečná (booked) balance
      $vyp_cd= substr($x,0,1);
      $balance= substr($x,10);
      $vypis['stav']= $stav= str_replace(',','.',$balance) * ($vyp_cd=='D' ? -1 : 1);
      $vypisy[]= $vypis;
//                                                 display("62F (booked) $stav");
      break;
    case '64':	// konečná (available) balance
//       $vyp_cd= substr($x,0,1);
//       $balance= substr($x,10,-1);
//       $stav= str_replace(',','.',$balance) * ($vyp_cd=='D' ? -1 : 1);
//                                                 display("64 (available) $stav");
      break;
    case '65':	// balance pro datum (Ledger balance pro datum)
//       $vypisy[]= $vypis;
      break;
    default:          // chyba
      fce_error("bank_ace: neznámý kód :$kod:");
      break;
    }
  }
  return $msg;
}
# -------------------------------------------------------------------------------------------------- bank_gpc
# rozkóduje soubor se strukturou GPC
# ( 074 075* )*
function bank_gpc($gpc,$soubor,&$yyyy) {
  global $y, $vypisy;
  $f= fopen($gpc, "r");
  $vypis= array();
  while ( !feof($f) ) {
    $buf= fgets($f,4096);
    if ( strlen($buf) ) {
      $druh= substr($buf,0,3);
      switch ( $druh ) {
      case '074':    // věta obratová
        // hlavička našeho účtu
        $nprikaz= 0;
        if ( count($vypis) ) {
          $vypisy[]= $vypis;
          $vypis= array();
        }
        $vypis['soubor']= $soubor;
        $vypis['ucet']= $ucet= gpc_kod2ucet(substr($buf,3,16));
        $prevod= array();
        $vypis['vypis']= $nvypis= substr($buf,105,3);
        $dat0= substr($buf,108,6);
        $_yy= substr($dat0,4,2);
        $yyyy= ((substr($_yy,0,1)=="0"||substr($_yy,0,1)=="1") ? "20" : "19").$_yy;
//                                                 display("GPC rec 074, dat0=$dat0 dá $yyyy");
        $vypis['datum']= "$yyyy-" . substr($dat0,2,2) . "-" . substr($dat0,0,2);
        $sta= substr($buf,60,14)/100;
        $vypis['stav']= $sta * (substr($buf,74,1)=="-" ? -1 : 1);
        $sta_poc= substr($buf,45,14)/100;
        $vypis['stav_poc']= $sta_poc * (substr($buf,59,1)=="-" ? -1 : 1);
        $vypis['*obrat']= 0;
        break;
      case '075':         // věta transakční
        // řádek výpisu
        $_kat= '';
        $nprikaz++;
        $prevod['popis']= substr($buf,97,20);
        $_inkaso= substr($buf,121,1);
        $prevod['typ']= substr($buf,60,1)=="1" || substr($buf,60,1)=="5"
          ? ($_inkaso%2==0 ? 3 : 1) : 5;
        $prevod['castka']= substr($buf,48,12)/100;
        $prevod['protiucet']= gpc_kod2ucet(substr($buf,19,16));
        $prevod['banka']= substr($buf,73,4);
        $dat0= substr($buf,122,6);
        $yyyy= ((substr($dat0,4,1)=="0"||substr($dat0,4,1)=="1") ? "20" : "19").substr($dat0,4,2);
//         $yyyy= (substr($dat0,4,1)=="0" ? "20" : "19").substr($dat0,4,2);
        $prevod['splatnost']= "$yyyy-" . substr($dat0,2,2) . "-" . substr($dat0,0,2);
        $prevod['ksym']= substr($buf,77,4);
        $prevod['vsym']= substr($buf,61,10);
        $prevod['ssym']= substr($buf,81,10);
        $cle= 0;
        $prevod['ucet']= $ucet;
        $prevod['vypis']= $nvypis;
        $prevod['ident']= str_pad($nprikaz,3,'0',STR_PAD_LEFT);
        $vypis['prevody'][$nprikaz]= $prevod;
        $vypis['*obrat']+= ($prevod['typ']<5 ? -1 : 1) * $prevod['castka'];
        $prevod= array();
        break;
      default:
        fce_error("importování GPC výpisu: neznámý kód věty: '$buf'");
      }
    }
  }
  fclose($f);
  if ( count($vypis) ) $vypisy[]= $vypis;
}
# -------------------------------------------------------------------------------------------------- gpc_kod2ucet
function gpc_kod2ucet ($kod) {
  $kod2= substr($kod,10,6)."-"
   .substr($kod,4,5).substr($kod,3,1).substr($kod,9,1).substr($kod,1,2).substr($kod,0,1);
  return $kod2;
}
?>
