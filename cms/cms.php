<?php

  // nastavení zobrazení PHP-chyb klientem při &err=1
  if ( isset($_GET['err']) && $_GET['err'] ) {
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 'On');
  }

  // rozlišení lokální a ostré verze
  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $ezer_root= 'cms';
  $skin= 'ck';

  // detekce dotykových zařízení
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ipad=       preg_match('/iPad/i',$_SERVER['HTTP_USER_AGENT']) || isset($_GET['ipad']);
  $ezer_ksweb= $android && $_SERVER["SERVER_NAME"]=="localhost"; // identifikace ladícího serveru KSWEB/Android

  // parametry aplikace
  $app=      'cms';
  $app_name= 'WEB';
  $CKEditor= isset($_GET['editor'])  ? $_GET['editor']  : '4';
  $dbg=      isset($_GET['dbg'])     ? $_GET['dbg']     : 1;                          /* debugger */
  $awesome=  isset($_GET['awesome']) ? $_GET['awesome'] : 3;
  $gapi=     isset($_GET['gapi'])    ? $_GET['gapi']    : !($ezer_local || $ezer_ksweb);
  $gmap=     isset($_GET['gmap'])    ? $_GET['gmap']    : !($ezer_local || $ezer_ksweb);

  // inicializace SESSION
  session_unset();
  session_start();
  $_SESSION[$app]['GET']= array();

  // cesty II
  $path= $_SERVER['PHP_SELF'];
  $path= substr($path,0,strrpos($path,'/'));
  $path= substr($path,0,strrpos($path,'/'));
  $rel_root= str_replace('//','/',$_SERVER['HTTP_HOST'].$path);
  $abs_root= str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$path);
  $_SESSION[$app]['abs_root']= $abs_root;
  $_SESSION[$app]['rel_root']= $rel_root;
  $_SESSION[$app]['app_path']= "";
  // kořeny pro LabelDrop
  $path_files_href= "$rel_root/docs/$app/";
  $path_files_s= "$abs_root$path_files_href";
  $path_files_h= substr($abs_root,0,strrpos($abs_root,'/'))."/files/$app/";
  set_include_path(get_include_path().PATH_SEPARATOR.$abs_root);

  require_once("$ezer_root/$app.inc");

  $panel= "http://$rel_root/$ezer_root";
  $client= "http://$rel_root/{$EZER->version}/client";
  $licensed= "$client/licensed";

  $js= array_merge(
    // ckeditor a mootools
    array("$licensed/ckeditor$CKEditor/ckeditor.js","$licensed/clientcide.js"),
    // pro verzi 2.1
    $EZER->version=='ezer2'
    ? array("$licensed/mootools/asset.js","$licensed/mootools/slider.js"):array(),
    // pro Android a iPad
    $android || $ipad
    ? array("$licensed/Mslider.js","$licensed/Mdrag.js") : array(),
    // pro Android a iPad
    $android || $ipad
    ? array("$licensed/hammer.js") : array(),
    // pro verzi 2.2
    $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker.js"):array(),
    // jádro Ezer
    array("$client/lib.js","$client/ezer_fdom1.js","$client/ezer.js","$client/area.js",
      "$client/ezer_report.js","$client/ezer_fdom2.js","$client/app.js","$client/lib.js"),
    // rozšíření pro CKEditor
    // debugger                                                                       /* debugger */
    $dbg ? array("$licensed/jush/mini_jush.js"):array(),                              /* debugger */
    // pluginy
    array("$licensed/zeroclipboard/ZeroClipboard.js","$licensed/mootree.js"),
    // Google API  - pokud používat API lokálně
    $gapi ? array("https://apis.google.com/js/client.js?onload=Ezer.Google.ApiLoaded") : array(),
    // Google Maps - pokud používat online mapy lokálně
    $gmap ? array("http://maps.googleapis.com/maps/api/js?sensor=false") : array(),
    // vlastní funkce aplikace
    array("$panel/cms.js")
  );
  $css= array(
    $dbg ? "$licensed/jush/mini_jush.css" : '',                                     /* debugger */
    "$ezer_root/mini.css", "$ezer_root/web.css",
    "$client/licensed/datepicker/datepicker_vista/datepicker_vista.css",
    "$client/licensed/font-awesome/css/font-awesome.min.css",
    $android ? "$client/android.css" : "",
    $ipad ? "$client/ipad.css" : ""
  );
  $options= (object)array(              // přejde do Ezer.options...
    'skill'        => "'w'",
    'autoskill'    => "'!w'",
    'awesome' => $awesome,              // použít v elementech ikony awesome fontu
    'curr_version' => 0,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'Google' => "{                      // definice oprávnění přístupu na Google
        CLIENT_ID:'298989499345-nv7qddggevsa9m9v5f1rrb67r0v888k9.apps.googleusercontent.com'
      }",
    'path_files_href' => "'$path_files_href'",  // relativní cesta do složky docs/{root}
    'path_files_s' => "'$path_files_s'",        // absolutní cesta do složky docs/{root}
    'path_files_h' => "'$path_files_h'"         // absolutní cesta do složky ../files/{root}
  );
  $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
        na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>{$EZER->options->mail}</a> "
      . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
      . ($EZER->options->skype ? "nebo použijte Skype&nbsp;<a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
      . "<br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
  $touch_title= "Tutorial ".date("H:i")."<button id='android_menu' class='fa'><i class='fa fa-bars'></i></button>";
  $pars= (object)array(
    'template' => "mini+",
    'app_root' => "$rel_root/$ezer_root",       // startovní soubory tut.php a tut.inc jsou ve složce tut
    'dbg' => $dbg,                                                                    /* debugger */
//     'watch_ip' => false,
//     'watch_key' => false,
//     'autologin' => 'Guest/',
    'watch_key' => 1,   // true = povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = povolit přístup jen ze známých IP adres
    'title_right' => $ipad || $android ? $touch_title
                  : ($ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name),
    'contact' => $kontakt,
    'gc_maxlifetime'    => 12*60*60,    // životnost SESSION v sekundách - 12 hodin
    'CKEditor' => "{
      version:'$CKEditor',
      EzerMail:{toolbar:[['PasteFromWord',
        '-','Format','Bold','Italic','TextColor','BGColor',
        '-','JustifyLeft','JustifyCenter','JustifyRight',
        '-','Link','Unlink','HorizontalRule','Image',
        '-','NumberedList','BulletedList',
        '-','Outdent','Indent',
        '-','Source','ShowBlocks','RemoveFormat']]
      }
    }"
  );
  root_php($app,$app_name,'chngs',$skin,$options,$js,$css,$pars);

?>
