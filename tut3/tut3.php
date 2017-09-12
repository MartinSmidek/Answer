<?php
// foreach (array('DOCUMENT_ROOT','SCRIPT_FILENAME','HTTP_HOST') as $x) echo "$x=$_SERVER[$x]<br>";

  // nastavení výpisů echo
  global $echo;
  $echo= 0;
  if ( isset($_GET['echo']) ) $echo= $_GET['echo'];
  // nastavení zobrazení PHP-chyb klientem při &err=1
  if ( isset($_GET['err']) && $_GET['err'] ) {
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 'On');
  }

  // rozlišení lokální a ostré verze
  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  // detekce dotykových zařízení
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ipad=       preg_match('/iPad/i',$_SERVER['HTTP_USER_AGENT']) || isset($_GET['ipad']);
  $ezer_ksweb= $android && $_SERVER["SERVER_NAME"]=="localhost"; // identifikace ladícího serveru KSWEB/Android

  // parametry aplikace TUT
  $app=      'tut3';
  $CKEditor= isset($_GET['editor'])  ? $_GET['editor']  : '4';
  $dbg=      isset($_GET['dbg'])     ? $_GET['dbg']     : 1;                          /* debugger */
  $awesome=  isset($_GET['awesome']) ? $_GET['awesome'] : 3;
  $gapi=     isset($_GET['gapi'])    ? $_GET['gapi']    : !($ezer_local || $ezer_ksweb);
  $gmap=     isset($_GET['gmap'])    ? $_GET['gmap']    : !($ezer_local || $ezer_ksweb);
  $verze=    isset($_GET['ezer'])    ? $_GET['ezer']    : '?';
  $skin=     isset($_GET['skin'])    ? $_GET['skin']    : 'default';
  $mootools= isset($_GET['mootools'])? $_GET['mootools']: true;
  $app_name= "Testy Ezer$verze";

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
//   $path= $_SERVER['PHP_SELF'];
//   $path= substr($path,0,strrpos($path,'/'));
//   $path= substr($path,0,strrpos($path,'/'));

//   // cesty I
//   $path= $_SERVER['PHP_SELF'];
//   $path= substr($path,0,strrpos($path,'/'));
//   $path= substr($path,0,strrpos($path,'/'));
//   $abs_root= $_SERVER['DOCUMENT_ROOT'].$path;
//   $rel_root= $_SERVER['HTTP_HOST'].$path;

  $_SESSION[$app]['abs_root']= $abs_root;
  $_SESSION[$app]['rel_root']= $rel_root;
  $_SESSION[$app]['app_path']= "";
  // kořeny pro LabelDrop
  $path_files_href= "$rel_root/docs/$app/";
  $path_files_s= "$abs_root$path_files_href";
  $path_files_h= substr($abs_root,0,strrpos($abs_root,'/'))."/files/$app/";
  set_include_path(get_include_path().PATH_SEPARATOR.$abs_root);
//                 echo("TUT.PHP: abs_root=$abs_root; rel_root=$rel_root; ar={$_SESSION[$app]['abs_root']}");
//                                         echo("<hr>TUT.PHP: path=$path; abs_root=$abs_root; rel_root=$rel_root; include_path=".get_include_path());
//                                         echo phpinfo();

  require_once("tut3/$app.inc");

  $tut= "http://$rel_root/tut";
  $client= "http://$rel_root/{$EZER->version}/client";
  $licensed= "$client/licensed";

  $js= $EZER->version=='ezer3'
  // ------------------------------------------------------ JS verze Ezer 3
  ? array_merge(
    // ckeditor a mootools a ...
    array("$licensed/ckeditor$CKEditor/ckeditor.js"),
    $mootools ? array("$licensed/clientcide.js") : array(),
    array("$licensed/pikaday/pikaday.js"),
    array("$licensed/jquery-3.2.1.min.js","$licensed/jquery-noconflict.js","$client/licensed/jquery-ui.min.js"),
    // jádro Ezer 3 s relikty verze 2.2
    array(
      "$client/ezer_fdom1.js",
      "$client/ezer.js",
      "$client/ezer_fdom2.js",
      "$client/app.js",
      "$client/lib.js"
    ),
    array(
      "$client/ezer_app3.js",
      "$client/ezer3.js",
      "$client/ezer_area3.js",
      "$client/ezer_rep3.js",
      "$client/ezer_fdom3.js",
      "$client/ezer_lib3.js",
      "$client/ezer_tree3.js"
    ),
    // debugger                                                                       /* debugger */
//     $dbg ? array("$licensed/jush/mini_jush.js"):array(),                              /* debugger */
    // rozhodnout zda používat online mapy
    $gmap ? array("https://maps.googleapis.com/maps/api/js?sensor=false") : array()
    // uživatelské skripty
  )
  // ----------------------------------------------------- JS verze Ezer 2.2
  : array_merge(
    // ckeditor a mootools
    array("$licensed/ckeditor$CKEditor/ckeditor.js","$licensed/clientcide.js"),
    // clipboard.js
    array("$licensed/clipboard.min.js"),
    // pro verzi 2.1
    $EZER->version=='ezer2'
    ? array("$licensed/mootools/asset.js","$licensed/mootools/slider.js"):array(),
    // pro verzi 2.2
    $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker.js"):array(),
    // jádro Ezer
    array("$client/lib.js","$client/ezer_fdom1.js","$client/ezer.js","$client/ezer_report.js",
      "$client/area.js", "$client/ezer_fdom2.js","$client/app.js",
      /*"$licensed/zeroclipboard/ZeroClipboard.js",*/"$licensed/mootree.js"),
    // debugger                                                                       /* debugger */
    $dbg ? array("$licensed/jush/mini_jush.js"):array(),                              /* debugger */
    // rozhodnout zda používat online mapy
    $gmap ? array("https://maps.googleapis.com/maps/api/js?sensor=false") : array()
    // uživatelské skripty
  );

//   $js= array_merge(
//     // ckeditor a mootools
//     array("$licensed/ckeditor$CKEditor/ckeditor.js"),
//     $mootools ? array("$licensed/clientcide.js") : array(),
// //     // pro Android a iPad
// //     $android || $ipad
// //     ? array("$licensed/Mslider.js","$licensed/Mdrag.js") : array(),
// //     // pro Android a iPad
// //     $android || $ipad
// //     ? array("$licensed/hammer.js") : array(),
//     // data picker
//     $EZER->version=='ezer3'
//     ? array("$licensed/pikaday/pikaday.js")
//     : array("$licensed/datepicker.js"),
//     // jádro Ezer 2.2 a 3
//     array("$client/ezer_fdom1.js",
//       "$client/ezer.js","$client/area.js",
//       "$client/ezer_report.js","$client/ezer_fdom2.js",
//       "$client/app.js",
//       "$client/lib.js"
//     ),
//     // jádro Ezer 3
//     $EZER->version=='ezer3'
//     ? array("$licensed/jquery-3.2.1.min.js",
//             "$licensed/jquery-ui.min.js",
//             "$licensed/jquery-noconflict.js",
//       "$client/ezer_app3.js","$client/ezer3.js","$client/ezer_area3.js","$client/ezer_rep3.js",
//       "$client/ezer_fdom3.js","$client/ezer_lib3.js","$client/ezer_tree3.js"):array(),
//     // rozšíření pro CKEditor
//     array("$tut/i_fce.js"),
//     // debugger                                                                       /* debugger */
//     $dbg ? array("$licensed/jush/mini_jush.js"):array(),                              /* debugger */
//     // pluginy
//     // jen pro jádro Ezer 2.2
//     $EZER->version=='ezer2.2' ? array("$licensed/clipboard.min.js","$licensed/mootree.js") : array(),
// //     // jádro Ezer 2.2 i 3
// //     array("$licensed/mootree.js"),
// //     // Google API  - pokud používat API lokálně
// //     $gapi ? array("https://apis.google.com/js/client.js?onload=Ezer.Google.ApiLoaded") : array(),
// //     // Google Maps - pokud používat online mapy lokálně
// //     $gmap ? array("http://maps.googleapis.com/maps/api/js?sensor=false") : array()
// //     array("http://maps.googleapis.com/maps/api/js?libraries=geometry&sensor=false")
//      array()
//   );
  $css= $EZER->version=='ezer3'
    ? array(
      $dbg ? "$licensed/jush/mini_jush.css" : '',                                       /* debugger */
      "$client/ezer.css",
      "$client/ezer3.css.php=skin",
      "$client/licensed/pikaday/pikaday.css",
      "$client/licensed/jquery-ui.min.css",
      "$client/licensed/font-awesome/css/font-awesome.min.css",
      $android ? "$client/android.css" : "",
      $ipad ? "$client/ipad.css" : ""
   )
   : array(
      $dbg ? "$licensed/jush/mini_jush.css" : '',                                       /* debugger */
      "$client/ezer.css",
      "$client/licensed/datepicker/datepicker_vista/datepicker_vista.css",
      "$client/licensed/font-awesome/css/font-awesome.min.css",
      $android ? "$client/android.css" : "",
      $ipad ? "$client/ipad.css" : ""
   );
  $options= (object)array(              // přejde do Ezer.options...
    'awesome' => $awesome,              // použít v elementech ikony awesome fontu
    'curr_version' => 0,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
//     'Google' => "{                      // definice oprávnění přístupu na Google
//         CLIENT_ID:'298989499345-nv7qddggevsa9m9v5f1rrb67r0v888k9.apps.googleusercontent.com'
//       }",
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
    'favicon' => $EZER->version=='ezer3' ? 'tut_3_local.png' : 'tut3_local.png',
    'app_root' => "$rel_root/tut",                 // startovní soubory tut.php a tut.inc jsou ve složce tut
    'dbg' => $dbg,                                                                    /* debugger */
    'watch_ip' => false,
    'watch_key' => false,
    'autologin' => 'Guest/',
    'title_right' => $ipad || $android ? $touch_title
                  : ($ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name),
//     'title_right' => ($ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name)
//              . (($android||$ipad) ? "<button class='fa' id='android_menu'><i class='fa fa-bars'></i></button>" : ""),
    'contact' => $kontakt,
    'gc_maxlifetime'    => 12*60*60,    // životnost SESSION v sekundách - 12 hodin
//         extraPlugins:'ezersave,imageresize', removePlugins:'image'
//         extraPlugins:'imageresize'
//         extraPlugins:'image2', removePlugins:'image,forms'
    'CKEditor' => "{
      version:'$CKEditor',
      Minimal:{toolbar:[['Bold','Italic','Source']]},
      IntranetSlim:{
        toolbar:[['Bold','Italic','-','Link','Unlink','-','Source']],
        removePlugins:'wsc,elementspath,scayt'
      },
      Tutorial:{
        toolbar:[['Bold','Italic','TextColor','BGColor',
          '-','JustifyLeft','JustifyCenter','JustifyRight',
          '-','Link','Unlink','Image',
          '-','NumberedList','BulletedList',
          '-','Source']],
        extraPlugins:'justify'
      },
      EzerHelp2:{
        toolbar:[['PasteFromWord','-','Bold','Italic','TextColor','BGColor',
          '-','JustifyLeft','JustifyCenter','JustifyRight',
          '-','Link','Unlink','HorizontalRule','Image',
          '-','NumberedList','BulletedList',
          '-','Outdent','Indent',
          '-','Source','ShowBlocks','RemoveFormat']],
        extraPlugins:'ezersave,imageresize', removePlugins:'image'
      }
    }"
  );
  $const= (object)array();
//   $const->fifty= 100/3;
//   $const->const_rows= 7;
//   $const->const_left= 200;
//   $const->const_width= 200;
  if (  $EZER->version=='ezer3' )
    root_php3($app,$app_name,'test',$skin,$options,$js,$css,$pars,$const);
  else
    root_php($app,$app_name,'test',$skin,$options,$js,$css,$pars,$const);

?>
