<?php
# ------------------------------------------------------------------------------------------ IP test

$ips= array(0,
//   '88.86.120.249',                                   // chlapi.online
//   '89.176.167.5','94.112.129.207',                   // zdenek
  '83.208.101.130','80.95.103.170',                     // martin
  '127.0.0.1','192.168.1.146'                           // local
);

// $ip= my_ip();
// $ip_ok= in_array($ip,$ips);
// if ( !$ip_ok ) die('Error 404');

# -------------------------------------------------------------------- identifikace ladícího serveru
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
$all= true;
$icon= $ezer_local ? "cms/img/ses_local.png" : "cms/img/ses.png";
$time= isset($_SESSION['ans']['stamp']) ? time()-$_SESSION['ans']['stamp'] : '';
$web= isset($_SESSION['web']) ? debug($_SESSION['web'],'WEB') : 'null WEB';
$ans= isset($_SESSION['ans']) ? debug($_SESSION['ans'],"ANS $time") : 'null ANS';
$cms= $all ? debug($_SESSION,'SESSION')
    : (isset($_SESSION['cms']) ? debug($_SESSION['cms'],'CMS') : 'null CMS');

echo <<<__EOD
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <link rel="shortcut icon" href="$icon">
  <style>
    body { background:silver; 
      font-family:Arial,Helvetica,sans-serif; padding:0; margin:0;
      position:static; overflow:hidden; }
    button { position:relative; font-size:9pt; white-space:nowrap; z-index:1; padding:1px 4px; }
      @-moz-document url-prefix() { button { padding:0px 4px; } }
      button::-moz-focus-inner { border:0; padding:0; }
    .dbg { margin:0; overflow-y:auto; font-size:8pt; line-height:13px; }
    .dbg table { border-collapse:collapse; margin:1px 0;}
    .dbg td { border:1px solid #aaa; font:x-small Arial;color:#777;padding:1px 3px; line-height:11px; }
    .dbg td.title { color:#000; background-color:#aaa; }
    .dbg td.label { color:#a33;}
    .dbg table.dbg_array { background-color:#ddeeff; }
    .dbg table.dbg_object { background-color:#ffffaa; }
  </style>
  <script type="text/javascript">
    $js
  </script>
  <title>SESSION</title>
  </head>
  <body>
    <div id='cmd'>
      <button onclick="op('reload.');">reload</button>
      <button onclick="op('clear.web');">clear WEB</button>
      <button onclick="op('clear.ans');">clear ANS</button>
      <button onclick="op('clear.cms');">clear CMS</button>
      <button onclick="op('destroy.');">destroy SESSION</button>
      <button onclick="op('phpinfo.');">phpinfo</button>
      <button onclick="op('all.');">all SESSION</button>
    </div>
    <div id='paticka'>
      <div class='dbg' style="position:absolute;top:50px;width:30%;left:0%">$web</div>
      <div class='dbg' style="position:absolute;top:50px;width:25%;left:30%">$ans</div>
      <div class='dbg' style="position:absolute;top:50px;width:45%;left:60%">$cms</div>
    </div>
  </body>
</html>
__EOD;

# -------------------------------------------------------------------------------------------- debug
# vygeneruje čitelný obraz pole nebo objektu
# pokud jsou data v kódování win1250 je třeba použít  debug($s,'s',(object)array('win1250'=>1));
# options:
#   gettype=1 -- ve třetím sloupci bude gettype(hodnoty)
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
//   $x= strtr($x,'<>','«»'); //$x= str_replace('{',"'{'",$x);
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
    $len= 0;
    foreach($gt as $g => $t) {
      $len++;
      if ( $len>$length ) break;
        $x.= "<tr><td valign='top' class='label'>$g:</td><td>"
        . debugx($t,NULL,$html,$depth,$length,$win1250,$gettype) //TEST==1 ? $t : htmlspecialchars($t)
        .($gettype ? "</td><td>".gettype($t) : '')                      //+typ
        ."</td></tr>";
    }
    $x.= "</table>";
    $debug_level--;
  }
  else {
    if ( is_object($gt) )
      $x= "object:".get_class($gt);
    else
      $x= $html ? htmlspecialchars($gt,ENT_NOQUOTES,'UTF-8') : $gt;
  }
  return $x;
}
# -------------------------------------------------------------------------------------------- my ip
# zjištění klientské IP
function my_ip() {
  return isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
}
?>
