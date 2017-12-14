<?php # Systém An(w)er/Ezer, (c) 2008-2017 Martin Šmídek <martin@smidek.eu>

# inicializace systémů Ans(w)er
#   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr/db2
#   $app_name   = jméno aplikace
#   $db_name    = hlavní databáze
#   $skin       = ck|ch|db
#   $js_lib     = pole s *.js
#   $css_lib    = pole s *.css
#   $options    = doplnění Ezer.options
function answer_php($app,$app_name,$db_name,$skin,$js_lib,$css_lib,$options) {
  global $EZER,$ezer_local,$path_files_h,$path_files_s,$path_files_href;

  $server_name= isset($_SERVER["HTTP_X_FORWARDED_SERVER"])
    ?$_SERVER["HTTP_X_FORWARDED_SERVER"]:$_SERVER["SERVER_NAME"];
  $ezer_local= preg_match('/^\w+\.(ezer|bean)/',$server_name); // identifikace ladícího serveru
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ipad=       preg_match('/iPad/i',$_SERVER['HTTP_USER_AGENT']);

  $title_style= $ezer_local ? 'color:#ef7f13;' : '';
  $title_flag=  $ezer_local ? 'lokální ' : '';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4';
  $dbg=      isset($_GET['dbg']) ? $_GET['dbg'] : 0;
  $gmap=     isset($_GET['gmap']) ? $_GET['gmap'] : !$ezer_local;
  $awesome=  isset($_GET['awesome']) ? $_GET['awesome'] : 3;
  $verze=    isset($_GET['ezer'])    ? $_GET['ezer']    : '?';
//  $mootools= isset($_GET['mootools'])? $_GET['mootools']: true;
  $EZER= (object)array();

  // inicializace SESSION
  session_unset();
  session_start();
  $_SESSION[$app]['GET']= array('ezer'=>"'$verze'");

  // zapnutí příznaku pro ochranu souborů v docs (do konce session)
  setcookie("EZER",$app,0,"/");

  // ošetření běhu s testovací databází - zobrazit příznak
  if ( substr($app,-5)=='_test' ) {
    $title_style.= 'background-color:#ffffaa';
    $title_flag.= "testovací ";
  }

  $title_style= $title_style ? " style='$title_style'" : '';

  // cesty II
  $path= $_SERVER['PHP_SELF'];
  $path= substr($path,0,strrpos($path,'/'));
  $path= substr($path,0,strrpos($path,'/'));
  $rel_root= str_replace('//','/',$_SERVER['HTTP_HOST'].$path);
  $abs_root= str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$path);
  $_SESSION[$app]['abs_root']= $abs_root;
  $_SESSION[$app]['rel_root']= $rel_root;
  $_SESSION[$app]['app_path']= "";

  set_include_path(get_include_path().PATH_SEPARATOR.$abs_root);

  require_once("$app.inc.php");
  require_once("{$EZER->version}/server/ae_slib.php");

  $client= "{$EZER->version}/client";
  $licensed= "$client/licensed";

  $js= $EZER->version=='ezer3'
  // ------------------------------------------------------ JS verze Ezer 3
  ? array_merge(
    // ckeditor a mootools a ...
    array("$licensed/ckeditor$CKEditor/ckeditor.js"),
//    $mootools ? array("$licensed/clientcide.js") : array(),
    array("$licensed/pikaday/pikaday.js"),
    array("$licensed/jquery-3.2.1.min.js","$licensed/jquery-noconflict.js","$client/licensed/jquery-ui.min.js"),
    // jádro Ezer3
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
    $gmap ? array(
      "https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js",
      "https://maps.googleapis.com/maps/api/js?sensor=false") : array(),
    // skripty pro Answer
    array("db2/db2_fce3.js"),
    // uživatelské skripty
    $js_lib
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
    $gmap ? array("https://maps.googleapis.com/maps/api/js?sensor=false") : array(),
    // skripty pro Answer
    array("db2/db2_fce.js"),
    // uživatelské skripty
    $js_lib
  );

  $css= array_merge(
    $EZER->version=='ezer3' ? array("$client/ezer.css.php","$client/ezer3.css.php=skin") : array("$client/ezer.css"),
//     $EZER->version=='ezer2.2' ? $css_lib : array(),    // = uživatelské css
    $EZER->version=='ezer3' ? array("db2/db2.css","db2/db2.css.php=skin") : $css_lib,    // = uživatelské css
    $dbg ? array("./$licensed/jush/mini_jush.css") : array(),
    array("./$client/licensed/font-awesome/css/font-awesome.min.css"),
    $EZER->version=='ezer3'
    ? array("$client/licensed/pikaday/pikaday.css","$client/licensed/jquery-ui.min.css")
    : array("$client/licensed/datepicker/datepicker_vista/datepicker_vista.css"),
    // css pro dotykové klienty
    $android ? array("$client/android.css") : array(),
    $ipad ? array("$client/ipad.css") : array()
  );

  global $answer_db;

  // doplnění Ezer.options a $EZER->options
  $options->awesome=    $awesome;        // zda použít v elementech ikony awesome fontu
  $options->answer_db=  "'$answer_db'";  // hlavní pracovní databáze
  if ( !isset($options->curr_version) )  // pokud nebylo definováno v {root}.php
    $options->curr_version= 0;           // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
  $options->curr_users= 1;               // zobrazovat v aktuální hodině aktivní uživatele
  $options->group_db=   "'ezer_answer'"; // databáze se společnými údaji pro skupinu aplikací Answer
  $options->path_files_href= "'$path_files_href'"; // relativní cesta do složky docs/{root}
  $options->path_files_s=    "'$path_files_s'";    // absolutní cesta do složky docs/{root}
  $options->path_files_h=    "'$path_files_h'";    // absolutní cesta do složky ../files/{root}
  $options->help= "{width:600,height:500}"; // větší HELP

  $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
        na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>
        {$EZER->options->mail}</a> "
      . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
      . ($EZER->options->skype ? "nebo použijte Skype&nbsp;
         <a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
      . "<br/><br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
  $menu= "<button id='android_menu' class='fa'><i class='fa fa-bars'></i></button>";

  if ( $app=='db2' || $app=='db2_test' ) {
    // nová verze db2
    $cookie= 3;
    $app_last_access= "{$app}_last_access";
    if ( isset($_COOKIE[$app_last_access])
      && $_COOKIE[$app_last_access]>0 &&  $_COOKIE[$app_last_access]<4 ) {
      $cookie= $_COOKIE[$app_last_access];
    }
    $access_app= array(1=>"Setkání","Familia","(společný)");
    $access_app= $access_app[$cookie];
    $choice_js= "personify('menu_on'); return false;";
    $v= $EZER->version=='ezer3' ? "<sub>3</sub>" : '';
    $title= "
      <span $title_style>"
      . $title_flag
      ."<span id='access' onclick=\"$choice_js\" oncontextmenu=\"$choice_js\">
          Answer$v $access_app
        </span>
        <div id='access_menu'>
          <span onclick='personify(1);'>Ans(w)er Setkání</span>
          <span onclick='personify(2);'>Ans(w)er Familia</span>
          <span onclick='personify(3);' class='separator'>Ans(w)er (společný)</span>
        </div>
      </span>"
      . ($android || $ipad ? $menu : "");
  }
  else {
    // starší verze ys, ys2, fa, fa2 a cr - Centrum pro rodinu
    $title= "<span $title_style>$title_flag $app_name</span>" . ($android || $ipad ? $menu : "");
  }

  $pars= (object)array(
    'favicon' => $EZER->version=='ezer3'
      ? ($ezer_local ? 'db3_local.png' : 'db3.png')
      : ($ezer_local ? 'db2_local.png' : 'db2.png'),
    'dbg' => $dbg,      // true = povolit podokno debuggeru v trasování a okno se zdrojovými texty
    'watch_key' => 1,   // true = povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = povolit přístup jen ze známých IP adres
    'title_right' => $title,
    'contact' => $kontakt,
    'CKEditor' => "{
      version:'$CKEditor',
      Minimal:{toolbar:[['Bold','Italic','Source']]},
      IntranetSlim:{
        toolbar:[['Bold','Italic','-','Link','Unlink','-','Source']],
        removePlugins:'wsc,elementspath,scayt'
      },
      'EzerMail':{toolbar:[['PasteFromWord',
        '-','Format','Bold','Italic','TextColor','BGColor',
        '-','JustifyLeft','JustifyCenter','JustifyRight',
        '-','Link','Unlink','HorizontalRule','Image',
        '-','NumberedList','BulletedList',
        '-','Outdent','Indent',
        '-','Source','ShowBlocks','RemoveFormat']]
      }
    }"
  );
  if (  $EZER->version=='ezer3' )
    root_php3($app,$app_name,'chngs',$skin,$options,$js,$css,$pars,null,false);
  else
    root_php($app,$app_name,'chngs',$skin,$options,$js,$css,$pars,null,false);
}

?>
