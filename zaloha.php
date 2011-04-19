<?php
# ------------------------------------------------------------------------------------------------ #
# Zaloha databází systému Ezer pro Ans(w)er                                                        #
#                                                                                                  #
#                                                   (c) 2007-2011 Martin Šmídek <martin@smidek.eu> #
# ------------------------------------------------------------------------------------------------ #

$ezer_root= $_POST['root'];                        // jméno adresáře a hlavního objektu aplikace
if ( !$ezer_root ) $ezer_root= $_GET['root'];
require_once("$ezer_root.inc");
require_once("ezer2/ezer2_fce.php");

# zaloha.php?restore=path
#   obnoví databázi ze souboru $path_backup/subpath
  if ( $_GET['restore'] ) {
    $subpath= $_GET['restore'];
    $path= "$path_backup/$subpath";
    list($dws,$n,$file)= explode('/',$subpath);
    if ( $dws=='special' ) $file= $n;
    list($file,$ext)= explode('.',$file);       // oddělení přípony
    list($file,$note)= explode('-',$file);      // oddělení poznámky (serveru)
    $db= substr($file,0,-14);
    $dbi= $dbs[$ezer_local?1:0][$db];
    $host= $dbi[1]=='localhost' ? '' : "--host={$dbi[1]}";
    if ( isset($dbi) ) {
      $cmd= "$ezer_mysql_path/mysql ";
      $cmd.= "-u {$dbi[2]} --password={$dbi[3]} $host $db --show-warnings < $path";
      $html.= $cmd;
      $status= system($cmd);
      $html.= "<br><br>soubor $path_backup/{$_GET['restore']} byl zpracován s výsledkem: '$status'";
    }
    else
      $html= "chyba: $path; databáze $db není přístupná";
  }
# zaloha.php?typ=
#   listing  - přehled existujících záloh
#   restore  - přehled existujících záloh s možností obnovit data
#   kaskada  - uložení dnešní zálohy, (je-li pondělí přesun poslední pondělní do jeho týdne)
#              -- days:  dny v týdnu
#              -- weeks: pondělky týdnů roku
#   special  - uložení okamžité zálohy do složky special
#   kontrola - kontrola existence dnešní zálohy
  else if (in_array($_GET['typ'],array('listing','kaskada','special','kontrola'))) {
    $html= sys_backup_make((object)array('typ'=>$_GET['typ']));
  }
  else {
    $html= "zaloha.php musí být voláno s parametrem typ=x, kde x=listing|kaskada|special|kontrola,"
      . " nebo s parametrem restore=path pro obnovu databáze ze souboru";
  }
  echo "\xEF\xBB\xBF";    // DOM pro UTF-8
  echo $html;
?>
