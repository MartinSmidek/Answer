<?php # Systém FiS/Ezer2, (c) 2008-2010 Martin Šmídek <martin@smidek.eu>

global $ezer_root;

# příkazový řádek
#
#  menu=m[.s[.l.g.i]]   -- tabs, panel, menu.left, menu.group, menu.item
#  trace=ssxxxx         -- ++UTu
#  theight=x            -- výška trasovacího pruhu v px
#  skin                 -- ck|blue
#  session=1            -- zobrazení $_SESSION v informačním přihlašovacím okně

$menu=    $_GET['menu'] ? "start:'{$_GET['menu']}'," : '';
$xtrace=  $_GET['trace'] ? "to_trace:1,show_trace:1,ae_trace:'{$_GET['trace']}'," : '';
$skin=    $_GET['skin']=='blind' ? 'blind' : 'default';
$theight= $_GET['theight']?$_GET['theight']:240;

# identifikace ostrého serveru
$local= $_SERVER["SERVER_NAME"]=='ys2.ezer';

# parametrizace
#       minify          -- true dovoluje kompresi CSS a JS do souborů v kořenu
#       root            -- složka se zdrojovými texty
#       skin            -- jméno skinu nebo null
#       title           -- bude použito na více místech jako název aplikace
#       session         -- ezer|php (default)

$minify= false;          // true pokud je povolená minifikace
$ezer_root= 'ys';
$title= "Ans(w)er";
$session= "php";

$title_right= $local ? "<span style='color:#ef7f13'>$title</span>" : $title;
$favicon= $local ? "{$ezer_root}_local.png" : "{$ezer_root}.png";
session_start();
require "$ezer_root.inc";
$_SESSION['trace_height']= $theight;
$_SESSION[$ezer_root]['skin']= $skin;
$refresh= $_SESSION[$ezer_root]['sess_state']=='on' ? 'true' : 'false';

# identifikace prohlížeče
$browser=
  preg_match('/MSIE/',$_SERVER['HTTP_USER_AGENT'])?'IE':(
  preg_match('/Opera/',$_SERVER['HTTP_USER_AGENT'])?'OP':(
  preg_match('/Firefox/',$_SERVER['HTTP_USER_AGENT'])?'FF':(
  preg_match('/Chrome/',$_SERVER['HTTP_USER_AGENT'])?'CH':(
  '?'))));

session_start();
$_SESSION['skin']= $skin;
$refresh= $_SESSION[$ezer_root]['sess_state']=='on' ? 'true' : 'false';

require "$ezer_root.inc";

$js= array(
  'ezer2/client/licensed/ckeditor/ckeditor.js',
  'ezer2/client/licensed/clientcide.js',
  'ezer2/client/licensed/mootools/asset.js',
  'ezer2/client/licensed/mootools/slider.js',
  'ezer2/client/lib.js',
  'ezer2/client/ezer_fdom1.js',
  'ezer2/client/ezer.js',
  'ezer2/client/ezer_report.js',
  'ezer2/client/ezer_fdom2.js',
  'ezer2/client/app.js',
  'ys/ys_fce.js',
  'ds/fce.js'
);
$css= array(
  './ezer2/client/ezer.css.php',
  './ezer2/client/natdocs.css',
  './ezer2/client/licensed/fancyupload.css',
  './ys/ys.css.css'
);

$dbg= $_GET['dbg'];

# pro ladění a ostrý server je možné nastavit odlišné výchozí podmínky zde
# skill: oprávnění, který uživatel musí mít, aby aplikaci vůbec spustil
# ? autoskill: oprávnění, které dostává automaticky ten, kdo aplikaci spustí

$options= $local
? /* lokálně */<<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
    must_log_in:true,
    skin:'$skin',
    skill:'y',
    autoskill:'!y',
    $menu
    $xtrace
    refresh: $refresh,
    mini_debug:true, status_bar:true, to_trace:true
__EOD
: <<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
    must_log_in:true,
    skin:'$skin',
    skill:'y',
    autoskill:'!y',
    $menu
    $xtrace
    refresh: $refresh,
    mini_debug:false, status_bar:true, to_trace:true
__EOD;

# spojení všech CSS a JS do jediného souboru pokud je $minify==true a $_GET['dbg'] je prázdné

if ( $minify && !$dbg ) {
  if ( !$local ) define('MINIFY_BASE_DIR','/home/www/ezer/www-fis/2');
//   define('MINIFY_USE_CACHE', false);
  require_once('ezer2/server/licensed/minify.php');
  $minifyCSS= new Minify(TYPE_CSS);
  $minifyJS= new Minify(TYPE_JS);
  $minifyCSS->addFile($css);
  $minifyJS->addFile($js);
  file_put_contents("$ezer_root.css",$css= $minifyCSS->combine());
  file_put_contents("$ezer_root.js",$js= $minifyJS->combine());
  // header pro běh bez laděni
  $head= <<<__EOD
  <script src="$ezer_root.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="$ezer_root.css" type="text/css" media="screen" charset="utf-8" />
__EOD;
}
else {
  // header pro běh s laděním
  $head= "";
  foreach($js as $x) {
    $head.= "\n  <script src='$x' type='text/javascript' charset='utf-8'></script>";
  }
  foreach($css as $x) {
    $head.= "\n  <link rel='stylesheet' href='$x' type='text/css' media='screen' charset='utf-8' />";
  }
}

// obsah informačního okna - prioritu má zpráva z fis.inc označená z proměnné $ezer_info
global $ezer_path_todo, $ezer_path_serv, $ezer_info;
require_once("$ezer_path_serv/ae_slib.php");
require_once("$ezer_path_serv/reference.php");
require_once("$ezer_path_serv/sys_doc.php");
$kontakt= "
    Pokud byste narazili na problém, kontaktujte mě prosím ihned na<br/>
    Skype martin_smidek nebo na <br/>
    mobil 603 150 565 nebo mi napište na martin@smidek.eu<br/>
    <br/>Děkuji za spolupráci, Gándí
";
if ( $_GET['session'] ) {
  // zobraz stav session
  $info= "<div class='dbg'>".debugx($_SESSION,'$_SESSION:')."</div>";
}
else if ( $ezer_info )
  $info= "<div class='login_a_msg'>$ezer_info</div>";
else {
  ezer_connect();
  $dnu= 12;
  $info= doc_todo_show("cast!=1 AND SUBDATE(NOW(),$dnu)<=kdy_skoncil AND kdy_skoncil!='0000-00-00' ");
  if ( !$info )
    $info= "<div class='login_a_msg'>
      Během posledních $dnu dnů nedošlo v&nbsp;systému k&nbsp;podstatným změnám.<br/><br/>
      $kontakt</div>";
  else
    $info.= "<hr/>$kontakt";
}

# template HTML stránky typické aplikace
echo "\xEF\xBB\xBF";    // DOM pro UTF-8
echo <<<__EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs-CZ" lang="cs-CZ" dir="ltr">
<head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta http-equiv="content-language" content="cs" />
  <meta name="robots" content="noindex,nofollow">
  <meta name="author" content="Martin Šmídek" />
  <meta name="copyright" content="Copyright © 2010" />
  <meta name="generator" content="Ezer" />
  <link rel="shortcut icon" href="./$ezer_root/img/$favicon" />
  <title>$title</title>
  <script type="text/javascript">
    var Ezer= {};
    Ezer.fce= {};
    Ezer.str= {};
    Ezer.root= '$ezer_root';
    Ezer.options= {
      $options
    };
    Ezer.browser= '$browser';
  </script>
  $head
</head>
<body id="body" class='nogrid' onclick="$('DbgMenu').setStyle('display','none');/*Ezer.app.bodyClick();*/">
<!-- menu a submenu -->
  <div id='horni' class="MainBar">
    <div id="appl">$title_right</div>
    <div id='logo' oncontextmenu="$('DbgMenu').setStyle('display','block');return false;">
      <img class="StatusIcon" id="StatusIcon_idle" src="./$ezer_root/img/-logo.gif" />
      <img class="StatusIcon" id="StatusIcon_server" src="./$ezer_root/img/+logo.gif" />
      <ul id='DbgMenu' class="ContextMenu" style="position:absolute; top:5px; display:none; left:15px; z-index:2000; visibility:visible; opacity:1;">
        <li onclick="Ezer.app.reload();$('DbgMenu').setStyle('display','none');" style="border-bottom:1px solid #AAAAAA">
          <a>recompile</a></li>
        <li onclick="Ezer.run.$.dragBlock(true,false);$('DbgMenu').setStyle('display','none');">
          <a>drag</a></li>
        <li onclick="Ezer.App.save_drag();$('DbgMenu').setStyle('display','none');" style="border-bottom:1px solid #AAAAAA">
          <a>save</a></li>
        <li onclick="Cookie.dispose('PHPSESSID',{path: '/'});alert('Obnovte prosím svoje přihlášení do systému...');window.location.href= window.location.href;">
          <a>relogin</a></li>
        <li onclick="Ezer.dbg.stop=true;$('DbgMenu').setStyle('display','none');">
          <a>stop</a></li>
        <li onclick="Ezer.dbg.stop=false;$('DbgMenu').setStyle('display','none');">
          <a>continue</a></li>
      </ul>
    </div>
    <ul id="menu" class="MainMenu"></ul>
    <ul id="submenu" class="MainTabs"></ul>
  </div>
  <div id='ajax_bar'></div>
<!-- login -->
  <div id="login" style="display:none">
    <div id="login_1">
      <h1>Přihlášení ...</h1>
      <div class="login_a">
        <form  method="post" onsubmit="return false;">
          <span>uživatelské jméno</span><br />
          <input id="name" type="text" tabindex="1" title="jméno" name="name" value="" /><br />
          <span>heslo</span><br />
          <input id="pass" type="password" tabindex="2" title="heslo" name="pass"  value="" /><br />
          <span id="login_msg"></span><br />
          <input id="login_on" type="submit" tabindex="3" value="Přihlásit se" />
          <input id="login_no" type="button" tabindex="4" value="... ne, děkuji" />
        </form>
      </div>
    </div>
    <div id="login_2">
      <h1 style='text-align:right'>... informace</h1>
      <div class="login_a">
        $info
      </div>
    </div>
  </div>
<!-- pracovní plocha -->
  <div id="stred">
    <div id="shield"></div>
    <div id="work"></div>
  </div>
<!-- paticka -->
  <div id="dolni">
    <div id="warning"></div>
    <div id="kuk_err"></div>
    <div id="paticka">
      <div id="error"></div>
    </div>
    <div id="status_bar" style="width:100%;height:16px;padding: 1px 0pt 0pt;">
      <div id='status_left' style="float:left;"></div>
      <div id='status_center' style="float:left;">zpráva</div>
      <div id='status_right' style="float:right;"></div>
    </div>
    <pre id="kuk"></pre>
  </div>
  <div id="report" class="report"></div>
  <form><input id="drag" type="button" /></form>
  <form id="drag_form" class="ContextMenu" style="display:none;position:absolute;width:200px">
    <input id="drag_title" type="text" style="float:right;width:165px" />
    <div style="padding:3px 0 0 2px;width:30px">title:</div>
  </form>
<!-- konec -->
</body>
</html>
__EOD;
?>
