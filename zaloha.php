<?php
# ------------------------------------------------------------------------------------------------ #
# Zaloha databází systému Ezer pro Answer                                                          #
#                                                                                                  #
#                                                   (c) 2007-2020 Martin Šmídek <martin@smidek.eu> #
# ------------------------------------------------------------------------------------------------ #

global $EZER, $dbs, $ezer_server;
error_reporting(E_ALL & ~E_NOTICE);
$html= '';
$ezer_root= isset($_POST['root']) ? $_POST['root'] : $_GET['root'];
$_POST['root']= $_GET['root']= $ezer_root;
session_start();
require_once("$ezer_root.inc.php");
require_once("{$EZER->version}/ezer2_fce.php");

$html.= "ezer_server=$ezer_server; #dbs=".count($dbs)."; ";
$typ= isset($_GET['typ']) ? $_GET['typ'] : '';

# zaloha.php?restore=path
#   obnoví databázi ze souboru $path_backup/subpath
if ( isset($_GET['restore']) ) {
  $subpath= $_GET['restore'];
  $path= "$path_backup/$subpath";
  $pars= explode('/',$subpath);
  $dws= $pars[0];
  if ( $dws=='special' ) {
    $file= $pars[1];
  }
  else {
    $n= $pars[1];
    $file= $pars[2];
  }
  list($file,$ext)= explode('.',$file); // oddělení přípony
  $db_name_note= explode('-',$file);    // oddělení poznámky (data)
  $db_name= $db_name_note[0];           // oddělení poznámky (data)
                $html.= "db_name=$db_name; ";
  // nalezení záznamu databáze $db v $dbs
  $db= 0;
  foreach ($dbs[$ezer_server] as $name=>$desc) {
    if ( $name==$db_name  ) {
      $db= $desc;
    }
    elseif ( isset($desc[5]) && $desc[5]==$db_name ) {
      $db= $desc;
    }
  }
//                  $html.= "db=$db; ";
  if ( $db ) {
    $host= $db[1]=='localhost' ? '' : "--host={$db[1]}";
                  $html.= "host=$host; ";
    $cmd= "$ezer_mysql_path/mysql ";
    $cmd.= "-u {$db[2]} --password={$db[3]} $host $db_name --show-warnings < $path";
                $html.= $cmd;
    $status= system($cmd);
    $html.= "<br><br>soubor $path_backup/{$_GET['restore']} byl zpracován s výsledkem: '$status'";
  }
  goto end;
//    $html.= "file=$file; ";
//    $db= substr($file,0,-14);
                  $html.= "db=$db; ";
    $dbi= $dbs[$EZER->server][$db];
    $host= $dbi[1]=='localhost' ? '' : "--host={$dbi[1]}";
                  $html.= "host=$host; ";
    if ( isset($dbi) ) {
      $cmd= "$ezer_mysql_path/mysql ";
      $cmd.= "-u {$dbi[2]} --password={$dbi[3]} $host $db --show-warnings < $path";
                  $html.= $cmd;
      $status= system($cmd);
      $html.= "<br><br>soubor $path_backup/{$_GET['restore']} byl zpracován s výsledkem: '$status'";
    }
    else
      $html.= "chyba: $path; databáze $db není přístupná";
  }
# zaloha.php?typ=
#   listing  - přehled existujících záloh
#   download - přehled existujících záloh s možností jejich stažení ve formátu zip
#   restore  - přehled existujících záloh s možností obnovit data
#   kaskada  - uložení dnešní zálohy, (je-li pondělí přesun poslední pondělní do jeho týdne)
#              -- days:  dny v týdnu
#              -- weeks: pondělky týdnů roku
#   special  - uložení okamžité zálohy do složky special
#   kontrola - kontrola existence dnešní zálohy
  else if (in_array($typ,array('listing','kaskada','special','kontrola'))) {
    $html= sys_backup_make((object)array('typ'=>$typ));
  }
  elseif ( isset($_GET['download']) ) {
    $subpath= $_GET['download'];
    $path= "$path_backup/$subpath";
    list($dws,$n,$file)= explode('/',$subpath);
    if ( $dws=='special' ) $file= $n;
    $sql= file_get_contents($path);
//    $bzip2= bzcompress($sql);
    $gzip= gzencode($sql);
    header("Content-type: application/zip");
//    header('Content-Encoding: bzip2');
    header('Content-Encoding: gz');
    header('Content-Length: '.strlen($gzip));
    header("Content-Transfer-Encoding: binary");
//    header("Content-Disposition: attachment; filename=$file.bzip2");
    header("Content-Disposition: attachment; filename=$file.gz");
//    echo $bzip2;
    echo $gzip;
  }
  else {
    $html= "zaloha.php musí být voláno s parametrem typ=x, kde x=listing|download|kaskada|special|kontrola,"
      . " nebo s parametrem restore=path pro obnovu databáze ze souboru";
  }
end:  
  echo "\xEF\xBB\xBF";    // DOM pro UTF-8
  echo $html;
?>
