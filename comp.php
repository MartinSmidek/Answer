<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 4.01 Strict">
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Compiler Ezer2</title>
  <link rel="stylesheet" href="./comp.css" type="text/css" media="screen" charset="utf-8"><title>Comp2</title>
</head>
<body>
<?php
  require_once("config.php");

  global $display, $trace, $json, $ezer_path_serv, $ezer_path_appl, $ezer_path_code;
  require_once("$ezer_path_serv/comp2.php");
  require_once("$ezer_path_serv/comp2def.php");
  require_once("$ezer_path_serv/ae_slib.php");
  require_once("$ezer_path_serv/licensed/JSON.php");

  $json= new Services_JSON();
  // verze kompilátoru
  clearstatcache();
  $xname= "$ezer_path_serv/comp2.php";
  $xtime= @filemtime($xname);
  // projití složky
  $files= array();
  if ($dh= opendir($ezer_path_appl)) {
    while (($file= readdir($dh)) !== false) {
      if ( substr($file,-5)=='.ezer' ) {
        $name= substr($file,0,strlen($file)-5);
        $etime= @filemtime("$ezer_path_appl/$name.ezer");
        $ctime= @filemtime("$ezer_path_code/$name.code"); if ( !$ctime) $ctime= 0;
        $files[$name]= !$ctime || $ctime<$etime || $ctime<$xtime ? "old" : "ok";
      }
    }
    closedir($dh);
  }
  ksort($files);
  // zobrazení složky
  $menu= "<table>";
  foreach($files as $name=>$status) {
    $clr= $status=='ok' ? '#afa' : '#faa';
    $menu.= "<tr>
        <td style='background-color:$clr'><a href='comp.php?module=$name'>$name</a></td>
        <td>$status</td>
      </tr>";
  }
  $menu.= "</table>";
  // kompilace
  if ( $name= $_GET['module'] ) {
    $txt= comp_module($name);
    echo $display;
  }
  echo "<table><tr>
    <td valign='top'>$menu</td>
    <td valign='top'>$trace</td>
    <td valign='top'>$txt</td>
  </tr></table>";

function comp_module($name) {
  global $display, $trace, $json, $ezer_path_appl, $ezer_path_code;
  if ( !$name ) {
    $name= "m.q";
//     $name= "$";
  }
  global $code;
  $state= comp_file($name);
  $src= file_get_contents("$ezer_path_appl/$name.ezer");
  $src= str_replace(' ','&nbsp;',$src);
  $src= nl2br($src);
  $txt= ''; $note= false;
  for ($i= 0; $i<strlen($src); $i++) {
    $ch= $src[$i];
    if ( $ch=='#' ) $note= true;
    if ( $ch=='<' ) $note= false;
    if ( !$note ) $txt.= $ch;
  }
  debug($code,"COMPILED $name");
  display("$state");
  return $txt;
}

?>

  </body>
</html>
