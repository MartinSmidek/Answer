<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>

$minify= false;
$root= 'ys';
$title= "Ans(w)er";
require "$root.inc";

$js= array(
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
  './ezer2/client/appf.css',
  './ezer2/client/natdocs.css',
  './ezer2/client/licensed/fancyupload.css',
  './ys/ys.css.css'
);
$dbg= $_GET['dbg'];
$matous= $_SERVER["DOCUMENT_ROOT"]=='/home/www/ezer/www-ys';
$options= $matous ? <<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
    must_log_in:true,
    mini_debug:false, status_bar:true, to_trace:true
__EOD
: <<<__EOD
    debug:window.parent!=window,      // je nadřazený frame - dbg.html
    login_interval:600,
//     must_log_in:true,
    must_log_in:false, uname:'gandi', pword:'radost',
    mini_debug:true, status_bar:true, to_trace:true
__EOD;
if ( $matous) $title.= "/Ezer2";

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
  file_put_contents("$root.css",$css= $minifyCSS->combine());
  file_put_contents("$root.js",$js= $minifyJS->combine());
  // header pro běh bez laděni
  $head= <<<__EOD
  <script src="$root.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="$root.css" type="text/css" media="screen" charset="utf-8" />
__EOD;
}

// obsah informačního okna - prioritu má zpráva z fis.inc označená z proměnné $ezer_info
global $ezer_path_todo, $ezer_path_serv, $ezer_info;
require_once("$ezer_path_serv/ae_slib.php");
require_once("$ezer_path_serv/reference.php");
require_once("$ezer_path_serv/sys_doc.php");
$kontakt= "
    Pokud byste narazili na problém, kontaktujte mě prosím ihned na<br/>
    Skype martin_smidek nebo na <br/>
    mobil 603 150 565<br/>
    Děkuji za spolupráci, Gándí
";
if ( $ezer_info )
  $info= "<div class='login_a_msg'>$ezer_info</div>";
else {
  $dnu= 30;
  $info= doc_todo_show('++done','',0,$dnu,$ezer_path_todo);
  if ( !$info )
    $info= "<div class='login_a_msg'>
      Během posledních $dnu dnů<br/>nedošlo v systému<br/>k podstatným změnám.<br/><br/>
      $kontakt</div>";
  else
    $info.= "<hr/>$kontakt";
}

// HTML template
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
  <link rel="shortcut icon" href="./$root/img/$root.png" />
  <title>$title</title>
  <script type="text/javascript">
    var Ezer= {};
    Ezer.fce= {};
    Ezer.str= {};
    Ezer.root= '$root';
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
    <img class="StatusIcon" id="StatusIcon_idle" src="./$root/img/-logo.gif" />
    <img class="StatusIcon" id="StatusIcon_server" src="./$root/img/+logo.gif" />
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
?>
