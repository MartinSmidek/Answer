<?php # (c) 2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- wu
# na UTF8 na stránce win1250 v tabulce
function wu($x,$user2sql=0) { #trace();
  if ( $user2sql ) {
    // pøeveï uživatelskou podobu na sql tvar
    $y= utf2win($x,true);
  }
  else {
    // pøeveï sql tvar na uživatelskou podobu (default)
    $y= win2utf($x,true);
  }
  return $y;
}
# -------------------------------------------------------------------------------------------------- dt
# na datum na stránce z timestamp v tabulce
function dt($x,$user2sql=0) { #trace();
  if ( $user2sql ) {
    // pøeveï uživatelskou podobu na sql tvar
    $y= win2utf($x,true);
  }
  else {
    // pøeveï sql tvar na uživatelskou podobu (default)
    $y= date("j.n.Y", $x);
  }
  return $y;
}
# -------------------------------------------------------------------------------------------------- ds_compare_list
function ds_compare_list($orders) {  #trace();
  $errs= 0;
  $html= "<dl>";
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds_compare($order);
      $html.= wu("<dt>Objednávka <b>$order</b>".($x->neco?" - aspoò nìco":" - nic")."</dt>");
      $html.= "<dd>{$x->html}</dd>";
      $errs+= $x->err;
    }
  }
  $html.= "</dl>";
  $msg= $errs ? "Celkem $errs objednávek tohoto období obsahuje chyby."
    : "Všechny objednávky v tomto období jsou úplné." ;
  $result= (object)array('html'=>$html,'msg'=>wu($msg));
  return $result;
}
# -------------------------------------------------------------------------------------------------- ds_compare
function ds_compare($order) {  #trace();
  ezer_connect('setkani');
  // údaje z objednávky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("$order není platné èíslo objednávky");
  $o= mysql_fetch_object($res);
  // projití seznamu
  $qry= "SELECT * FROM setkani.ds_osoba WHERE id_order=$order ";
  $reso= mysql_qry($qry);
  $n= $n_0= $n_3= $n_9= $n_15= $n_a= $noroom= 0;
  while ( $reso && $u= mysql_fetch_object($reso) ) {
    // rozdìlení podle vìku
    $n++;
    if ( $u->narozeni=='0000-00-00' )
      $vek= -1;
    else {
      $vek= $o->fromday-sql2stamp($u->narozeni);
      $vek= $vek/(60*60*24*365);
    }
    if ( $vek>15 ) $n_a++;
    elseif ( $vek>9 ) $n_15++;
    elseif ( $vek>3 ) $n_9++;
    elseif ( $vek>0 ) $n_3++;
    else $n_0++;
    // kdo nebydlí?
    if ( !$u->pokoj ) $noroom++;
  }
  // posouzení poètù
  $no= $o->adults + $o->kids_10_15 + $o->kids_3_9 + $o->kids_3;
  $age= $n_a==$o->adults && $n_15==$o->kids_10_15 && $n_9==$o->kids_3_9 && $n_3==$o->kids_3;
  // zhodnocení úplnosti
  $err= $n==0 || $n>0 && $n!=$no || $noroom || $n_0 || $n>0 && !$age ? 1 : 0;
  // textová zpráva
  $html= '';
  $html.= $n==0 ? "Seznam úèastníkù je prázdný. " : '';
  $html.= $n>0 && $n!=$no ? "Seznam úèastníkù není úplný. " : '';
  $html.= $noroom ? "Jsou zde neubytovaní hosté. " : '';
  $html.= $n_0 ? "Nìkteøí hosté nemají vyplnìno datum narození. " : '';
  $html.= $n>0 && !$age ? "Stáøí hostù se liší od pøedpokladù objednávky." : '';
  if ( !$html )$html= "Seznam úèastníkù odpovídá objednávce.";
  $form= (object)array('adults'=>$n_a,'kids_10_15'=>$n_15,'kids_3_9'=>$n_9,'kids_3'=>$n_3,
    'nevek'=>$n_0, 'noroom'=>$noroom);
  $result= (object)array('html'=>wu($html),'form'=>$form,'err'=>$err,'neco'=>$n?1:0);
  return $result;
}
# ================================================================================================== EXPORT EXCEL
# -------------------------------------------------------------------------------------------------- xls_workbook
function xls_workbook($table,&$formats) {  #trace();
  require_once('./licensed/xls/OLEwriter.php');
  require_once('./licensed/xls/BIFFwriter.php');
  require_once('./licensed/xls/Worksheet.php');
  require_once('./licensed/xls/Workbook.php');
  $wb= new Workbook("../../$table.xls");
  // formáty
  $formats= (object)array();
  $formats->tit= $wb->add_format();
  $formats->tit->set_bold();
  $formats->hd= $wb->add_format();
  $formats->hd->set_bold();
  $formats->hd->set_pattern();
  $formats->hd->set_fg_color('silver');
  $formats->dec= $wb->add_format();
  $formats->dec->set_num_format("# ##0.00");
  $formats->dmy= $wb->add_format();
  $formats->dmy->set_num_format("d.m.yyyy");
  $formats->dm= $wb->add_format();
  $formats->dm->set_num_format("d.m");
  $formats->right= $wb->add_format();
  $formats->right->set_align("right");
  return $wb;
}
# -------------------------------------------------------------------------------------------------- xls_workbook
function xls_header($ws,$r,$c,$desc,$format) {  #trace();
  // hlavièka
  $fields= explode(',',$desc);
  foreach ($fields as $dc => $fa) {
    list($title,$width)= explode(':',$fa);
    $ws->set_column($c+$dc,$c+$dc,$width);
    $ws->write_string($r,$c+$dc,$title,$format);
  }
}
# -------------------------------------------------------------------------------------------------- xls_klienti
# vytvoøí seznam klientù
function xls_klienti($orders,$obdobi) {  #trace();
  ezer_connect('setkani');
  $table= "klienti_$obdobi";
  $wb= xls_workbook($table,&$f);
  $ws= $wb->add_worksheet('klienti');
  $ws->write_string(0,0,"Seznam klientù zahajujících pobyt v období $obdobi",$f->tit);
  xls_header($ws,$or=2,0,"jméno:10,pøíjmení:13,adresa:40,datum narození:14,telefon:13,email:26,"
    ."termín:10,rekreaèní poplatek:15",$f->hd);
  // zjištìní klientù zahajujících pobyt v daném období
  $qry= "SELECT *,o.fromday as _of,o.untilday as _ou,
         p.fromday as _pf,p.untilday as _pu
         FROM setkani.ds_osoba AS p
         JOIN tx_gnalberice_order AS o ON uid=id_order
         WHERE FIND_IN_SET(id_order,'$orders') ORDER BY id_order,rodina,narozeni DESC";
  $res= mysql_qry($qry);
  while ( $res && $xo= mysql_fetch_object($res) ) {
    // pøipsání øádku
    $or++; $oc= 0;
    $ws->write_string($or,$oc++,"{$xo->jmeno}");
    $ws->write_string($or,$oc++,"{$xo->prijmeni}");
    $ws->write_string($or,$oc++,"{$xo->ulice}, {$xo->psc} {$xo->obec}");
    $dat= $xo->narozeni ? sql_date1($xo->narozeni): '';
    $ws->write_string($or,$oc++,$dat,$f->right);
    $ws->write_string($or,$oc++,$xo->telefon);
    $ws->write_string($or,$oc++,$xo->email);
    $pf= sql2stamp($xo->_pf); $pu= sql2stamp($xo->_pu);
    $od= $pf ? date('j.n',$pf) : date('j.n',$xo->_of);
    $do= $pu ? date('j.n',$pu) : date('j.n',$xo->_ou);
    $ws->write_string($or,$oc++,"$od - $do");
  }
  $wb->close();
  $html= " <a href='$table.xls'>seznam klientù</a>";
  return wu($html);
}
# -------------------------------------------------------------------------------------------------- ds_zaloha
function ds_zaloha($order) {  #trace();
  ezer_connect('setkani');
  // kontrola objednávky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $obj= mysql_fetch_object($res) ) {
    // založení faktury
    $table= "zaloha_$order";
    $wb= xls_workbook($table,&$f);
    $zaloha= $wb->add_worksheet('zaloha');
    $zaloha->write_string(0,0,"Záloha objednávky è.$order",$f->tit);
    xls_header($zaloha,2,0,'rodina:10,poèet:20',$f->hd);
    $wb->close();
    $html= "<a href='$table.xls'>zaloha akce $order</a>";
  }
  else
    $html= "neúplná objednávka $order";
  return $html;
}
# -------------------------------------------------------------------------------------------------- xls_faktura
# položka,poèet osob,poèet nocí,poèet nocolùžek,cena jednotková,celkem,DPH 9%,DPH 19%,základ
# *ubytování
function xls_faktura($ws,$desc) {  #trace();

}
# -------------------------------------------------------------------------------------------------- ds_faktury
function ds_faktury($order) {  #trace();
  ezer_connect('setkani');
  // kontrola objednávky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $obj= mysql_fetch_object($res) ) {
    // založení faktury
    $table= "faktury_$order";
    $wb= xls_workbook($table,&$f);
    // zjištìní poètu faktur za akci
    $faktury= $wb->add_worksheet('faktury');
    $qry= "SELECT rodina,count(*) as pocet FROM setkani.ds_osoba
           WHERE id_order=$order GROUP BY rodina ORDER BY rodina";
    $res= mysql_qry($qry);
    $faktury->write_string(0,0,"Fakturace objednávky è.$order",$f->tit);
    xls_header($faktury,2,0,'rodina:10,poèet:20',$f->hd);
    $fr= 3;
    while ( $res && $fs= mysql_fetch_object($res) ) {
                                                                debug($fs,'rod');
      // seznam faktur
      $rod= $fs->rodina ? $fs->rodina : $order;
      $rod_tit= $fs->rodina ? $fs->rodina : 'neoznaèené';
      $faktury->write_string($fr,0,$rod_tit);
      $faktury->write_number($fr,1,$fs->pocet);
      $fr++;
      // listy pro jednu rodinu
      // seznam
      $sez= $wb->add_worksheet("$rod-seznam");
      $sez->write_string(0,0,"Seznam úèastníkù pod znaèkou $rod",$f->tit);
      $qry= "SELECT * FROM setkani.ds_osoba
             WHERE id_order=$order AND rodina='{$fs->rodina}' ORDER BY narozeni DESC";
      $reso= mysql_qry($qry);
      xls_header($sez,$or=2,0,'pøíjmení a jméno:20,adresa:40,narození:10,telefon:10,email:30,'
        .'záloha:10,pokoj:5,lùžko:5,strava:5,poznámka:20',$f->hd);
      while ( $reso && $xo= mysql_fetch_object($reso) ) {
        $or++; $oc= 0;
        $sez->write_string($or,$oc++,"{$xo->prijmeni} {$xo->jmeno}");
        $sez->write_string($or,$oc++,"{$xo->ulice}, {$xo->psc} {$xo->obec}");
        $sez->write_number($or,$oc++,$xo->narozeni,'d.m.y');
        $sez->write_string($or,$oc++,$xo->telefon);
        $sez->write_string($or,$oc++,$xo->email);
        $sez->write_string($or,$oc++,'');                       // záloha
        $sez->write_string($or,$oc++,$xo->pokoj);               // pokoj
        $sez->write_string($or,$oc++,$xo->luzko);
        $sez->write_string($or,$oc++,$xo->strava ? $xo->strava : $obj->board);
        $sez->write_string($or,$oc++,$xo->pozn);
      }
      // faktura
      $sez= $wb->add_worksheet("$rod-faktura");
    }
    // souèty titulní stránky
    $faktury->write_string($fr,0,'celkem',$f->tit);
    $faktury->write_formula($fr,1,"= SUM(B3:B$fr)");
    $wb->close();
    $html= "<a href='$table.xls'>fakturace akce $order</a>";
  }
  else
    $html= "neúplná objednávka $order";
  return $html;
}
# -------------------------------------------------------------------------------------------------- yymmdd2date
function yymmdd2date($yymmdd) {  trace();
  $date= '';
  if ( $yymmdd ) {
    $y= substr($yymmdd,0,2);
    $m= substr($yymmdd,2,2);
    $d= substr($yymmdd,4,2);
    $y= $y<20 ? "20$y" : "19$y";
    $date= "$d.$m.$y";
  }
  return $date;
}
# -------------------------------------------------------------------------------------------------- ds_uid
function ds_uid() {  #trace();
  global $x;
  $i_uid= 1;
  $uid= $x->parm->$i_uid;
  if ( !$uid ) $uid= 711;
  return $uid;
}
# -------------------------------------------------------------------------------------------------- ds_server
function ds_server($db) {  #trace();
  return $server;
}
# -------------------------------------------------------------------------------------------------- ds_ucast_objednatele
function ds_ucast_objednatele($id_order,$ano) {  #trace();
  global $x;
  if ( $ano ) {
    ezer_connect('setkani');
    $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$id_order";
    $res= mysql_qry($qry);
    if ( $res && $o= mysql_fetch_object($res) ) {
      if ( $jmeno= $o->firstname ) $prijmeni= $o->name;
      else list($jmeno,$prijmeni)= explode(' ',$o->name);
      if ( !$prijmeni ) { $prijmeni= $jmeno; $jmeno= ''; }
      $qry= "INSERT INTO setkani.ds_osoba (
        id_order,rodina,prijmeni,jmeno,psc,obec,ulice,email,telefon) VALUES (
        $id_order,'','$prijmeni','$jmeno',
        '{$o->zip}','{$o->city}','{$o->address}','{$o->email}','{$o->telephone}')";
      $res= mysql_qry($qry);
    }
  }
  return $ano;
}
# -------------------------------------------------------------------------------------------------- ds_old
function ds_old ($uid) {
    $res= mysql_qry("SELECT * FROM setkani.tx_gnalberice_order WHERE  NOT deleted AND NOT hidden AND uid=$uid");
    $row= mysql_fetch_assoc($res);
//                                                         $html.= debug($row,"$uid",1);
    // vyzvednuti a transformace hodnot o objednavce
    $state= $row['state'];
//     $state_str= $this->TCA_state_config_items[$state];// $this->TCA['state']['config']['items'][$state][0];
    $note= $row['note'];
    $rooms= $row['rooms'];
    $fromday= date('d.m.Y',$row['fromday']);
    $untilday= date('d.m.Y',$row['untilday']);
    $adults= $row['adults'];
    $kids_10_15= $row['kids_10_15'];
    $kids_3_9= $row['kids_3_9'];
    $kids_3= $row['kids_3'];
    $board= $row['board'];
//     $board_str= $this->TCA_board_config_items[$board]; // $this->TCA['board']['config']['items'][$board][0];
    $ze_dne= date("j.n.Y", $row['crdate']);
    // osobni data
    $name= $row['name'];
    $address= $row['address'];
    $zip= $row['zip'];
    $city= $row['city'];
    $telephone= $row['telephone'];
    $email= $row['email'];
//     // editacni tlacitka - pouze kdyz je autor ze skupiny "spravce alberic"
//     $season= "from={$this->from}&until={$this->until}";
//     if ( $this->spravce_alberic ) {
//       $o_ref= "{$gn->index}?id=$pid&$season";
//       $o= "<span class=ven_ox><a href=$o_ref&form=$uid>&nbsp;o&nbsp;</a></span>";
//       $x= "<span class=ven_ox><a href=$o_ref&conf=$uid>&nbsp;x&nbsp;</a></span>";
//       $ox= "<table align=right><tr><td valign=top>$o&nbsp;$x</td></tr></table>";
//     }
//     else {
//       $ox= '';
//     }
    // vytvoreni formulare
    $html.= "
      <p>
      <div class=venture><div class=text_d>
        <h3  class=alberice>Objednávka è. $uid ze dne $ze_dne</h3>
        <table>
          <tr><td style='border-right:1px darkgreen solid'>
            <table width=250>
              <tr>
                <td class=alberice><span class=form_popis>stav objednávky</span><br>$state_str</td>
              </tr><tr>
                <td class=alberice><span class=form_popis>poznámka k objednávce</span><br>$note</td>
              </tr>
              <tr><td><table><tr>
                <td width=60 class=alberice><span class=form_popis>pøíjezd</span><br>$fromday</td>
                <td width=60 class=alberice><span class=form_popis>odjezd</span><br>$untilday</td>
                <td class=alberice><span class=form_popis>seznam pokojù</span><br>$rooms</td>
              </tr></table></td></tr>
              <tr><td><table><tr>
                <td class=alberice><span class=form_popis>dospìlých</span><br>$adults</td>
                <td class=alberice><span class=form_popis>dìti 10-15</span><br>$kids_10_15</td>
                <td class=alberice><span class=form_popis>dìti 3-9</span><br>$kids_3_9</td>
                <td class=alberice><span class=form_popis>dìti do 3 let</span><br>$kids_3</td>
              </tr></table></td></tr>
              <tr>
                <td class=alberice><span class=form_popis>typ stravy</span><br>$board_str</td>
                <td><span class=form_popis></span><br></td>
              </tr>
            </table>
          </td><td valign=top>
            <table width=250>
              <tr valign=top><td class=alberice colspan=2><span class=form_popis>jméno a pøíjmení</span><br>$name</td>
           </table>
          </td></tr>
        </table>
      </div></div>
      ";
   return $html;
}
# -------------------------------------------------------------------------------------------------- ds_obj_menu
# vygeneruje menu pro loòský, letošní a pøíští rok ve tvaru objektu pro ezer2 pro zobrazení objednávek
# urèující je datum zahájení pobytu v objednávce
function ds_obj_menu() {
  global $mysql_db;
  $stav= map_cis('ds_stav');
//                                                                 debug($stav,'ds_obj_menu');
  $mesice= array(1=>'leden','únor','bøezen','duben','kvìten','èerven',
    'èervenec','srpen','záøí','øíjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  ezer_connect('setkani');
  for ($y= 0; $y<=0; $y++) {
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyy= $letos+$y;
      $group= "$yyyy$mm";
      $gr= (object)array('type'=>'menu.group'
        ,'options'=>(object)array('title'=>wu($mesice[$m])." $yyyy"),'part'=>(object)array());
      $mn->part->$group= $gr;
      
      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $qry= "/*ds_obj_menu*/SELECT uid,fromday,untilday,state,name,state FROM setkani.tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
//              JOIN ezer_ys._cis ON druh='ds_stav' AND data=state
      $res= mysql_qry($qry);
      while ( $res && $o= mysql_fetch_object($res) ) {
        $iid= $o->uid;
        $zkratka= $stav[$o->state];
        $par= (object)array('uid'=>$iid);
        $tit= wu("$iid - ").$zkratka.wu(" - {$o->name}");
        $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$tit,'par'=>$par));
        $gr->part->$iid= $tm;
      }
    }
  }
  return $mn;
}
# -------------------------------------------------------------------------------------------------- ds_kli_menu
# vygeneruje menu pro loòský, letošní a pøíští rok ve tvaru objektu pro ezer2 pro zobrazení klientù
# urèující je datum zahájení pobytu v objednávce
function ds_kli_menu() {
  ezer_connect('setkani');
  $mesice= array(1=>'leden','únor','bøezen','duben','kvìten','èerven',
    'èervenec','srpen','záøí','øíjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  for ($y= 0; $y<=0; $y++) {
    $yyyy= $letos+$y;
    $group= $letos+$y;
    $gr= (object)array('type'=>'menu.group'
      ,'options'=>(object)array('title'=>$group),'part'=>(object)array());
    $mn->part->$group= $gr;
    for ($m= 1; $m<=12; $m++) {
      $od= "$group-".sprintf('%02d',$m)."-01";
      $do= "$group-".sprintf('%02d',$m)."-".date('t',mktime(0,0,0,$m,1,$group));
      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $uids= ''; $del= ''; $objednavek= $klientu= 0;
      $qry= "SELECT uid,(adults+kids_10_15+kids_3_9+kids_3) as celkem
             FROM tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
      $res= mysql_qry($qry);
      while ( $res && $o= mysql_fetch_object($res) ) {
        $uids.= "$del{$o->uid}"; $del= ',';
        $objednavek++;
        $celkem+= $o->celkem;
      }
      $qryp= "SELECT count(*) as klientu FROM ds_osoba
             WHERE  FIND_IN_SET(id_order,'$uids')";
      $resp= mysql_qry($qryp);
      if ( $resp && $op= mysql_fetch_object($resp) ) {
        $klientu= $op->klientu;
      }
      $tit= wu($mesice[$m])." - $celkem ($klientu)";
      $par= (object)array('od'=>$od,'do'=>$do,'celkem'=>$celkem,'klientu'=>$klientu,'objednavek'=>$objednavek,'uids'=>$uids);
      $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$tit,'par'=>$par));
      $gr->part->$m= $tm;
    }
  }
  return $mn;
}
?>
