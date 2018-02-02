<?php
# ------------------------------------------------------------------------------------------ IP test

$ips= array(0,
//   '88.86.120.249',                                      // chlapi.online
//   '89.176.167.5','94.112.129.207',                      // zdenek
  '83.208.101.130','80.95.103.170',                     // martin
  '127.0.0.1','192.168.1.146'                           // local
);

$ip= my_ip();
$ip_ok= in_array($ip,$ips);
if ( !$ip_ok ) die('Error 404');

# -------------------------------------------------------------------- identifikace lad�c�ho serveru
$ezer_localhost= preg_match('/^localhost|^192\.168\./',$_SERVER["SERVER_NAME"])?1:0;
$ezer_local= $ezer_localhost || preg_match('/^\w+\.ezer|ezer\.\w+/',$_SERVER["SERVER_NAME"])?1:0;
if ( !isset($_SESSION) ) session_start();
# ----------------------------------------------------------------------------------------------- js
$js= <<<__EOD
function op(op_arg) {
  if ( op_arg=='reload.' )
    location.href= "ses.php";
  else
    location.href= "ses.php?op="+op_arg;
}
__EOD;
# ------------------------------------------------------------------------------------------- server
if ( isset($_GET['op']) ) {
  list($op,$arg)= explode('.',$_GET['op']);
  switch ($op) {
  case 'clear':
    $_SESSION[$arg]= array();
    break;
  case 'destroy':
    session_destroy();
    break;
  case 'phpinfo':
    phpinfo();
  }
  header('Location: ses.php');
  exit();
}
# ------------------------------------------------------------------------------------------- client
$icon= $ezer_local ? "img/ses_local.png" : "img/ses.png";
$time= isset($_SESSION['ans']['stamp']) ? time()-$_SESSION['ans']['stamp'] : '';
$full= debug($_SESSION,'SESSION');
$web= isset($_SESSION['web']) ? debug($_SESSION['tut'],'TUT') : 'null TUT';
$ans= isset($_SESSION['ans']) ? debug($_SESSION['ans'],"ANS $time") : 'null ANS';
$cms= isset($_SESSION['cms']) ? debug($_SESSION['cms'],'CMS') : 'null CMS';

echo <<<__EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <link rel="shortcut icon" href="$icon">
  <link rel="stylesheet" href="../ezer3/client/ezer.css.php" type="text/css" media="screen" charset="utf-8">
  <style>
    .Label { position:relative; }
    button { position:relative; }
  </style>
  <script type="text/javascript">
    $js
  </script>
  <title>SESSION</title>
  </head>
  <body  style="overflow:auto">
    <div id='cmd'>
      <button onclick="op('reload.');">reload</button>
      <!--
      <button onclick="op('clear.web');">clear WEB</button>
      <button onclick="op('clear.ans');">clear ANS</button>
      <button onclick="op('clear.cms');">clear CMS</button>
      -->
      <button onclick="op('destroy.');">destroy SESSION</button>
      <button onclick="op('phpinfo.');">phpinfo</button>
    </div>
    <div id='paticka'>
      <div class='dbg' style="position:absolute;top:50px;left:0;padding:5px;">$full</div>
      <!--
      <div class='dbg' style="position:absolute;top:50px;width:30%;left:0%">$web</div>
      <div class='dbg' style="position:absolute;top:50px;width:25%;left:30%">$ans</div>
      <div class='dbg' style="position:absolute;top:50px;width:45%;left:60%">$cms</div>
      -->
    </div>
  </body>
</html>
__EOD;

# -------------------------------------------------------------------------------------------- debug
# vygeneruje �iteln� obraz pole nebo objektu
# pokud jsou data v k�dov�n� win1250 je t�eba pou��t  debug($s,'s',(object)array('win1250'=>1));
# options:
#   gettype=1 -- ve t�et�m sloupci bude gettype(hodnoty)
function debug($gt,$label=false,$options=null) {
  global $trace, $debug_level;
  $debug_level= 0;
  $html= ($options && $options->html) ? $options->html : 0;
  $depth= ($options && $options->depth) ? $options->depth : 64;
  $length= ($options && $options->length) ? $options->length : 64;
  $win1250= ($options && $options->win1250) ? $options->win1250 : 0;
  $gettype= ($options && $options->gettype) ? 1 : 0;
  if ( is_array($gt) || is_object($gt) ) {
    $x= debugx($gt,$label,$html,$depth,$length,$win1250,$gettype);
  }
  else {
//     $x= $html ? htmlentities($gt) : $gt;
    $x= $html ? htmlspecialchars($gt,ENT_NOQUOTES,'UTF-8') : $gt;
    $x= "<table class='dbg_array'><tr>"
      . "<td valign='top' class='title'>$label</td></tr><tr><td>$x</td></tr></table>";
  }
  if ( $win1250 ) $x= wu($x);
//   $x= strtr($x,'<>','��'); //$x= str_replace('{',"'{'",$x);
  $trace.= $x;
  return $x;
}
function debugx(&$gt,$label=false,$html=0,$depth=64,$length=64,$win1250=0,$gettype=0) {
  global $debug_level;
  if ( $debug_level > $depth ) return "<table class='dbg_over'><tr><td>...</td></tr></table>";
  if ( is_array($gt) ) {
    $debug_level++;
    $x= "<table class='dbg_array'>";
    $x.= $label!==false
      ? "<tr><td valign='top' colspan='".($gettype?3:2)."' class='title'>$label</td></tr>" : '';
    foreach($gt as $g => $t) {
      $x.= "<tr><td valign='top' class='label'>$g</td><td>"
      . debugx($t,NULL,$html,$depth,$length,$win1250,$gettype) //TEST==1 ? $t : htmlspecialchars($t)
      .($gettype ? "</td><td>".gettype($t) : '')                      //+typ
      ."</td></tr>";
    }
    $x.= "</table>";
    $debug_level--;
  }
  else if ( is_object($gt) ) {
    $debug_level++;
    $x= "<table class='dbg_object'>";
    $x.= $label!==false ? "<tr><td valign='top' colspan='".($gettype?3:2)."' class='title'>$label</td></tr>" : '';
//     $obj= get_object_vars($gt);
    $len= 0;
    foreach($gt as $g => $t) {
      $len++;
      if ( $len>$length ) break;
//       if ( is_string($t) ) {
//         $x.= "<td>$g:$t</td>";
//       }
//       if ( $g=='parent' ) {
//         $td= $t==null ? "<td class='label'>nil</td>" : (
//           is_object($t) && isset($t->id) ? "<td class='label'>{$t->id}</td>" : (
//           is_string($t) ? "<td>$t</td>" :
//           "<td class='label'>?</td>"));
//         $x.= "<tr><td class='dbg_over'>$g:</td>$td</tr>";
//       }
//       else {
        $x.= "<tr><td valign='top' class='label'>$g:</td><td>"
        . debugx($t,NULL,$html,$depth,$length,$win1250,$gettype) //TEST==1 ? $t : htmlspecialchars($t)
        .($gettype ? "</td><td>".gettype($t) : '')                      //+typ
        ."</td></tr>";
//       }
    }
    $x.= "</table>";
    $debug_level--;
  }
  else {
    if ( is_object($gt) )
      $x= "object:".get_class($gt);
    else
//       $x= $html ? htmlentities($gt) : $gt;
      $x= $html ? htmlspecialchars($gt,ENT_NOQUOTES,'UTF-8') : $gt;
//       if ( is_string($x) ) $x= "'$x'";
  }
  return $x;
}
# -------------------------------------------------------------------------------------------- my ip
# zji�t�n� klientsk� IP
function my_ip() {
  return isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
}
?>