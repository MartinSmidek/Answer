<?php

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

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
    'login_interval' => 120
  );
  $pars= (object)array(
//     'no_local' => true,                // true = nezohledňovat lokální přístup pro watch_key,watch_ip
    'watch_key' => true,               // true = povolit přístup jen po vložení klíče
    'watch_ip' => true,                // true = povolit přístup jen ze známých IP adres
    'title_right' => $ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name
  );
  root_php($app,$app_name,'test',$skin,$options,$js,$css,$pars);

?>
