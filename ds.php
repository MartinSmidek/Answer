<?php # (c) 2007-2011 Martin Smidek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $app=      'ds';
  $app_name= 'Ans(w)er';
  $skin=     'default';
  $skin=     'ds';

  require_once("$app.inc");
  require_once("ezer2/server/ae_slib.php");

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
    'ds/fce.js'
  );
  $css= array(
    './ezer2/client/ezer.css.php',
    './ezer2/client/licensed/fancyupload.css',
    './ds/ds.css.css'
  );
  $options= (object)array(
    'skill'      => "'y'",
    'autoskill'  => "'!y'",
    'must_log_in'=> 'false',
    'user_record'=> 'false',            // nejsou známy uživatelské údaje
  );
  $pars= (object)array(
    'watch_ip' => false,                // true = povolit přístup jen ze známých IP adres
    'template' => 'panel'
  );
  root_php($app,$app_name,'news',$skin,$options,$js,$css,$pars);

/*
$minify= false;
$root= 'ds';
$title= "Dům setkání";
$js= array(
  'ezer2/client/licensed/clientcide.js',
  'ezer2/client/lib.js',
  'ezer2/client/ezer_fdom1.js',
  'ezer2/client/ezer.js',
  'ezer2/client/ezer_report.js',
  'ezer2/client/ezer_fdom2.js',
  'ezer2/client/app.js',
  'ds/fce.js'
);
$css= array(
  './ezer2/client/appf.css',
  './ezer2/client/natdocs.css',
  './ds/ds.css.css'
);
$dbg= $_GET['dbg'];
$matous= $_SERVER["DOCUMENT_ROOT"]=='/home/www/';
$options= $matous ? <<<__EOD
    debug:window.parent!=window && dbg,      // je nadřazený frame - dbg.html
    mini_debug:dbg, status_bar:dbg, to_trace:dbg,
    must_log_in:false, uname:'dum', pword:'setkani'
__EOD
: <<<__EOD
    debug:window.parent!=window && dbg,      // je nadřazený frame - dbg.html
    mini_debug:dbg, status_bar:dbg, to_trace:dbg, ae_trace:'TUL*',show_trace:true,
    must_log_in:false, uname:'dum', pword:'setkani'
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

// HTML template
echo <<<__EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="shortcut icon" href="./$root/img/$root.png" />
  <title>$title</title>
  <script type="text/javascript">
    var Ezer= {};
    Ezer.parm= location.hash.split(',');
    Ezer.fce= {};
    Ezer.str= {};
    Ezer.root= '$root';
    var dbg= Ezer.parm[0]!='#';
    Ezer.options= {
      $options
    };
    function ondomready() {
      if ( !dbg ) {
        $('stred').setStyles({top:0});
        $('horni').setStyles({display:'none'});
        $('status_bar').setStyles({display:'none'});
        //$('status').setStyles({display:'none'});
        //$('drag').setStyles({display:'none'});
      }
    }
  </script>
  $head
</head>
<body id="body" class='nogrid' onload='ondomready();'>
<!-- bez menu a submenu -->
  <div id='horni' class="MainBar">
    <div id="StatusIcon">$title</div>
  </div>
  <div id='ajax_bar'></div>
<!-- pracovní plocha -->
  <div id="stred" style="top:35px">
    <div id="shield"></div>
    <div id="work"></div>
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
<!-- konec -->
</body>
</html>
__EOD;
*/
?>
