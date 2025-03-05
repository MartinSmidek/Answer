<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ========================================================================================> IMPORT **/
# transformace DS do Answer
define('org_ms50',16);
define ('POZOR',"<span style:'color:red;background:yellow'>POZOR</span>");
# ------------------------------------------------------------------------------------------- import
function import($tab) {
  // načtení importního souboru $tab.csv z ms50/doc/import
  $data= [];
  $url= "ms50/doc/import/$tab.csv";
  $fp= fopen($url,'r');
  if (!$fp) { display("$url unknown"); return; }
  $line= fgets($fp,4096);
  $line= substr($line,3);
  $fld= str_getcsv($line,';');
  debug($fld);
  while ($fp && !feof($fp) && ($line= fgets($fp,4096))) {
    $data[]= str_getcsv($line,';');
  }
  fclose($fp);
  // vymazání tabulky
  query("TRUNCATE TABLE $tab");
  // vložení do db
  $flds= implode(',',$fld);
  foreach ($data as $vs) {
    $vals= ''; $del= '';
    if ($tab=='rodina' && $vs[8]=='VPS') $vs[8]= 1;
    if ($tab=='osoba') $vs[14]= sql_date1($vs[14],1);
    foreach ($vs as $v) {
      $vals.= "$del'$v'";
      $del= ',';
    }
    switch ($tab) {
      case 'akce':
        $rok= substr($vs[1],3,4);
        $od= "$rok-07-01";
        $do= "$rok-07-08";
        $qry= "INSERT INTO akce (access,datum_od,datum_do,$flds) "
          . "VALUE (16,'$od','$do',$vals)";
        query($qry);
        break;
      case 'pobyt':
        $qry= "INSERT INTO pobyt ($flds) VALUE ($vals)";
        query($qry);
        break;
      case 'osoba':
        $qry= "INSERT INTO osoba (access,$flds) VALUE (16,$vals)";
        query($qry);
        break;
      case 'tvori':
        $qry= "INSERT INTO tvori ($flds) VALUE ($vals)";
        query($qry);
        break;
      case 'rodina':
        $qry= "INSERT INTO rodina (access,$flds) VALUE (16,$vals)";
        query($qry);
        break;
    }
  }
}
# ----------------------------------------------------------------------------------------- complete
function complete() {
  // doplnění spolu podle pobyt.i0_rodina
  $rr= pdo_qry("SELECT id_pobyt,i0_rodina FROM pobyt WHERE i0_rodina!=0");
  while ($rr && (list($idp,$idr)= pdo_fetch_array($rr)) ) {
    $tt= pdo_qry("SELECT id_osoba FROM tvori WHERE id_rodina=$idr");
    while ($tt && (list($ido)= pdo_fetch_array($tt)) ) {
      $je= select("COUNT(*)",'spolu',"id_osoba=$ido AND id_pobyt=$idp");
      if (!$je)
        query("INSERT INTO spolu (id_pobyt,id_osoba) VALUE ($idp,$ido)");
    }
  }
  // doplnění VPS do pobytů kurzu roku 2024 podle rodina.r_umi
  $rr= pdo_qry("SELECT id_pobyt "
      . "FROM pobyt JOIN rodina ON id_rodina=i0_rodina JOIN akce ON id_akce=id_duakce "
      . "WHERE r_umi='1' AND YEAR(datum_od)=2024 ");
  while ($rr && (list($idp)= pdo_fetch_array($rr)) ) {
    query("UPDATE pobyt SET funkce=1 WHERE id_pobyt=$idp");
  }
  
}
