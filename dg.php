<?php
# ------------------------------------------------------------------------------------------------ #
# Dialogy pro práci s informacemi z Answer volatelné z www.setkani.org                             #
#                                                                                                  #
#                                                                                                  #
#                                                   (c) 2007-2011 Martin Šmídek <martin@smidek.eu> #
# ------------------------------------------------------------------------------------------------ #

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $app=      'dg';
  $app_name= 'Dialog';
  $skin=     'default';
  $skin=     'ck';

  require_once("$app.inc");
  require_once("ezer2/server/ae_slib.php");

  $js= array(
//     'ezer2/client/licensed/ckeditor/ckeditor.js',
    'ezer2/client/licensed/clientcide.js',
    'ezer2/client/licensed/mootools/asset.js',
    'ezer2/client/licensed/mootools/slider.js',
    'ezer2/client/lib.js',
    'ezer2/client/ezer_fdom1.js',
    'ezer2/client/ezer.js',
    'ezer2/client/ezer_report.js',
    'ezer2/client/ezer_fdom2.js',
    'ezer2/client/app.js'
  );
  $css= array(
    './ezer2/client/ezer.css.php',
    './ezer2/client/licensed/fancyupload.css',
    './dg/dg.css.css'
  );
  $options= (object)array(
    'skill'      => "'y'",
    'autoskill'  => "'!y'",
    'must_log_in'=> 'false',
    'user_record'=> 'false',            // nejsou známy uživatelské údaje
    'to_trace'   => 'true'
  );
  $pars= (object)array(
    'watch_ip' => false,                // false = povolit přístup ze všech IP adres
    'template' => 'panel',
    'post_server' => array("http://www.setkani.org","http://setkani.ezer")
  );
  root_php($app,$app_name,'news',$skin,$options,$js,$css,$pars);
?>
