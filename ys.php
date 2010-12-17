<?php # Systém An(w)er/Ezer2, (c) 2008-2010 Martin Šmídek <martin@smidek.eu>

  $app=      'ys';
  $app_name= 'Ans(w)er';
  $skin=     'default';
  $ip=       true;                      // povolit přístup jen ze známých IP adres

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
    'ys/ys_fce.js',
    'ds/fce.js'
  );
  $css= array(
    './ezer2/client/ezer.css.php',
    './ezer2/client/licensed/fancyupload.css',
    './ys/ys.css.css'
  );
  $options= (object)array(
    'skill'      => "'y'",
    'autoskill'  => "'!y'",
  );
  root_php($app,$app_name,'news',$skin,$options,$js,$css,$ip);

?>
