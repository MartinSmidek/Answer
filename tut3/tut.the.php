<?php # (c) 2015 Martin Smidek <martin@smidek.eu>
/** ======================================================================================> PHPEXCEL **/
# testy a demonstrace programových balíků integrovaných do systému Ezer2.2
# ------------------------------------------------------------------------------------- tut_phpexcel
# testy balíku PHPExcel
function tut_phpexcel ($par) {  trace();
  global $ezer_path_root, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel.php";
  $y= (object)array();
  $file= "$ezer_path_root/$par->file";
  foreach(explode(',',$par->fce) as $fce) {
    $y->$fce= (object)array();
    switch ($fce) {
    case 'load':        # Load file to a PHPExcel Object
      $y->$fce->load= ($objPHPExcel= PHPExcel_IOFactory::load($file)) ? 'ok' : 'err';
      break;
    case 'A1':          # read, write, read cell A1
      $y->$fce->get1= $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
      $val= date("j.n.Y H:i");
      $y->$fce->set=  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(0, 1,$val) ? 'ok' : 'err';
      $y->$fce->get2= $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(0, 1)->getValue();
      break;
    case 'save5':       # save to try.xls
      $objWriter= PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
      $objWriter->save("$ezer_path_root/docs/try.xls");
      break;
    case 'save7':       # save to try.xlsx
      $objWriter= PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
      $objWriter->save("$ezer_path_root/docs/try.xlsx");
      break;
    }
  }
  $html.= "<div class='dbg'>".debugx($y,$file)."</div>";
  return $html;
}
# =====================================================================================> SELECT AUTO
# ---------------------------------------------------------------------------------------- test_auto
# test autocomplete
function test_auto($patt,$par) {  trace();
                                                      debug($par,"test_auto.par");
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadejte vzor";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT id_jmena AS _key,jmeno AS _value
           FROM _jmena
           WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
                                                        display("test_auto:$qry");
    $res= mysql_qry($qry);
    while ( $res && $t= mysql_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $a->{$t->_key}= $t->_value;
    }
                                                        display("test_auto:$n,$limit");
    // obecné položky
    if ( !$n )
      $a->{0}= "... nic nezačíná $patt";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
                                                      debug($a,"test_auto");
  return $a;
}
/** ========================================================================================> GOOGLE */
# -------------------------------------------------------------------------------- google_drive_list
# vrátí seznam souborů v doméně smidek.eu
# par= {folder:id}
# kde metoda popsána v https://developers.google.com/drive/v2/reference/files/list#try-it
function google_drive_list($par) {
  $list= "-";
  $TutorialApiKey= '298989499345-nv7qddggevsa9m9v5f1rrb67r0v888k9.apps.googleusercontent.com';
  $tab= file_get_contents(
    "https://www.googleapis.com/drive/v2/files?fields=items(parents%2Ctitle)&key=$TutorialApiKey");
                                        display($tab);
  return $list;
}
# ------------------------------------------------------------------------------------ google_sheet3
# vrátí text podle metody, default je testovací tabulka "cisla" v doméně smidek.eu
# par= {key:...,sheet:...,method=html|json|csv,[range=...],[query=...]}
# kde query je popsáno v https://developers.google.com/chart/interactive/docs/querylanguage
function google_sheet3($par) { trace();
  global $json;
  $tq= "tqx=out:$par->method";
  $tq.=  $par->sheet ? "&gid={$par->sheet}" : "";
  $tq.= $par->range ? "&range={$par->range}" : "";
  $tq.= $par->query ? '&tq='.urlencode($par->query) : "";
//   $tq.= '&tq='.urlencode("select A,C where A>1");
  $tab= file_get_contents(
    "https://docs.google.com/spreadsheets/d/$par->key/gviz/tq?$tq");
                                        display($tab);
  if ( $par->method=="csv" ) {
    $tab= nl2br($tab);
  }
  elseif ( $par->method=="json" ) {
    $tab= substr($tab,strlen("google.visualization.Query.setResponse("),-2);
    $tab= $json->decode($tab);
                                        debug($tab,$sheet);
    $tab= "<div class='dbg'>".debugx($tab)."</div>";
  }
  return $tab;
}
/** ======================================================================================> SECURITY */
# ------------------------------------------------------------------------------------- tut_security
# testy ochrany přístupu k souborům
function tut_security($cmd) {
  $msg= '';
  switch($cmd) {
  case 'on':
    setcookie("EZER","tut",0,"/");
    $msg.= "soubory jsou přístupné";
    break;
  case 'off':
    setcookie("EZER","",0,"/");
    $msg.= "soubory jsou blokovány";
    break;
  }
  return $msg;
}
// /** ======================================================================================> DEBUGGER */
// # ----------------------------------------------------------------------------------------- dbg_file
// # testy ochrany přístupu k souborům
// function dbg_file($name) {
//   global $ezer_path_root;
//   $ret= (object)array('html'=>'');
//   $ret->html= "
//     <ol id='dbg_src'>
//   ";
//   $style= "style='font-family:monospace;white-space:pre'";
//   $path= "$ezer_path_root/tut/$name.ezer";
//   $lns= file($path,FILE_IGNORE_NEW_LINES);
//   foreach($lns as $i=>$ln) {
//     $lnx= htmlentities($ln);
//     $ret->html.= "\n<li $style>$lnx</li>";
//   }
//   $ret->html.= "
//     </ol>
//     <!-- script>
//       window.opener.dbg_onclick_start();
//     </script -->
//   ";
//   return $ret;
// }
?>
