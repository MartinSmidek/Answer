<?php

  $app=      'test';
  $app_name= 'Test/Ans(w)er';
  $skin=     'default';

  require_once("$app.inc");
  require_once("ezer2/server/ae_slib.php");

  $js= array(
    'ezer2/client/licensed/ckeditor/ckeditor.js',
    'ezer2/client/licensed/clientcide.js',
    'ezer2/client/licensed/mootools/asset.js',
    'ezer2/client/licensed/mootools/slider.js',
    'ezer2/client/ezer_fdom1.js',
    'ezer2/client/ezer.js',
    'ezer2/client/ezer_report.js',
    'ezer2/client/ezer_fdom2.js',
    'ezer2/client/app.js',
    'ds/fce.js',
    'test/fce.js',
    'ezer2/client/lib.js'
  );
  $css= array(
    './ezer2/client/ezer.css.php',
    './ezer2/client/natdocs.css',
    './ezer2/client/licensed/fancyupload.css',
    './test/test.css'
  );
  $options= (object)array(
    'login_interval' => 60
  );
  root_php($app,$app_name,'test',$skin,$options,$js,$css);

/*
global $ezer_root;

// url-parametr má tvar name=m[.s[.l.g.i]] tedy 1, 2 nebo 5 jmen oddělených tečkou
// jednotlivá jména jsou ezer-identifikátory bloků tabs, panel, menu.left, menu.group, menu.item
$menu= $_GET['menu'] ? "start:'{$_GET['menu']}'," : '';

# identifikace ostrého serveru

$matous= $_SERVER["DOCUMENT_ROOT"]=='/home/www/';

# parametrizace
#       minify          -- true dovoluje kompresi CSS a JS do souborů v kořenu
#       root            -- složka se zdrojovými texty
#       skin            -- jméno skinu nebo null
#       title           -- bude použito na více místech jako název aplikace

$minify= false;
$ezer_root= 'test';
$title= "Test/Ans(w)er";

session_start();
$_SESSION['skin']= $skin;
$refresh= $_SESSION[$ezer_root]['sess_state']=='on' ? 'true' : 'false';

require "$ezer_root.inc";

$js= array(
  'ezer2/client/licensed/clientcide.js',
  'ezer2/client/lib.js',
  'ezer2/client/ezer_fdom1.js',
  'ezer2/client/ezer.js',
  'ezer2/client/ezer_report.js',
  'ezer2/client/ezer_fdom2.js',
  'ezer2/client/app.js',
  'ds/fce.js',
  'ezer2/client/licensed/mootools/asset.js',
//   'ezer2/client/licensed/fancyupload/source/Swiff.Uploader.js',
//   'ezer2/client/licensed/fancyupload/source/Fx.ProgressBar.js',
//   'ezer2/client/licensed/fancyupload/source/FancyUpload2.js',
//  'ezer2/client/licensed/fancyupload/source/FancyUpload3.Attach.js',
  'test/fce.js'
);
$css= array(
  './ezer2/client/ezer.css.php',
  './ezer2/client/natdocs.css',
  './ezer2/client/licensed/fancyupload.css',
  './test/test.css'
);
$dbg= $_GET['dbg'];
$options= $matous ? <<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
    must_log_in:true,
    skin:'$skin',
    $menu
    refresh: $refresh,
    mini_debug:false, status_bar:true, to_trace:true
__EOD
: <<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
    must_log_in: false, uname:'test', pword:'test',
    skin:'$skin',
    $menu
    refresh: $refresh,
    mini_debug:true, status_bar:true, to_trace:true
__EOD;
if ( $matous) $title.= "/Matouš";

if ( $dbg || !$minify ) {
  // header pro běh s laděním
  $head= "";
  foreach($js as $x) {
    $head.= "\n  <script src='$x' type='text/javascript' charset='utf-8'></script>";
  }
  foreach($css as $x) {
    $head.= "\n  <link rel='stylesheet' href='$x' type='text/css' media='screen' charset='utf-8' />";
  }
}
else {
  if ( $matous) define('MINIFY_BASE_DIR','/home/www/ezer/www-fis/2');
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

// HTML template
echo <<<__EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="shortcut icon" href="./$ezer_root/img/$ezer_root.png" />
  <title>$title</title>
  <script type="text/javascript">
    var Ezer= {};
    Ezer.fce= {};
    Ezer.str= {};
    Ezer.root= '$ezer_root';
    Ezer.options= {
      $options
    };
  </script>
  $head
</head>
<body id="body" class='nogrid'>
<!-- menu a submenu -->
  <div id='horni' class="MainBar">
    <div id="appl">$title</div>
    <div id='logo' oncontextmenu="$('DbgMenu').setStyle('display','block');return false;">
      <img class="StatusIcon" id="StatusIcon_idle" src="./$ezer_root/img/-logo.gif" />
      <img class="StatusIcon" id="StatusIcon_server" src="./$ezer_root/img/+logo.gif" />
      <ul id='DbgMenu' class="ContextMenu" style="position:absolute; top:5px; display:none; left:15px; z-index:2000; visibility:visible; opacity:1;">
        <li onclick="Ezer.app.reload();$('DbgMenu').setStyle('display','none');" style="border-bottom:1px solid #AAAAAA"><a>recompile</a></li>
        <li onclick="Cookie.dispose('PHPSESSID',{path: '/'});alert('Obnovte prosím svoje přihlášení do systému...');window.location.href= window.location.href;"><a>relogin</a></li>
        <li onclick="Ezer.dbg.stop=true;$('DbgMenu').setStyle('display','none');"><a>stop</a></li>
        <li onclick="Ezer.dbg.stop=false;$('DbgMenu').setStyle('display','none');"><a>continue</a></li>
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
      <h1>... informace</h1>
      <div class="login_a">
        Přeji příjemnou práci
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
<!-- konec -->
</body>
</html>
__EOD;
*/
?>
