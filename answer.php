<?php # Systém An(w)er/Ezer, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

# inicializace systémů Ans(w)er
#   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
#   $app_name   = jméno aplikace
#   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
#   $skin       = ck|ch
#   $js_lib     = pole s *.js
#   $css_lib    = pole s *.css
#   $options    = doplnění Ezer.options
function answer_php($app,$app_name,$db_name,$skin,$js_lib,$css_lib,$options) {
  global $EZER,$ezer_local,$path_files;

  $server_name= isset($_SERVER["HTTP_X_FORWARDED_SERVER"])
    ?$_SERVER["HTTP_X_FORWARDED_SERVER"]:$_SERVER["SERVER_NAME"];
  $ezer_local= preg_match('/^\w+\.ezer/',$server_name); // identifikace ladícího serveru
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ipad=       preg_match('/iPad/i',$_SERVER['HTTP_USER_AGENT']);
  $ezer_ksweb= $android && $server_name=="localhost";   // identifikace ladícího serveru KSWEB/Android

  //$app=      ys/ys2/fa/fa2/cr             // jméno adresáře a hlavního objektu aplikace == $ezer_root!
  //$app_name= 'Ans(w)er - Familia'.($ezer_ksweb?" / test Android":"");;
  //$skin=     'default';
  //$skin=     'ch';

  $app_name.= ($ezer_ksweb?" / test Android":"");
  $title_style= $ezer_local ? 'color:#ef7f13;' : '';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4';
  $dbg=      isset($_GET['dbg']) ? $_GET['dbg'] : 0;
  $gmap=     isset($_GET['gmap']) ? true : !($ezer_local || $ezer_ksweb);
  $awesome=  isset($_GET['awesome']) ? $_GET['awesome'] : 3;
  $EZER= (object)array();

  // inicializace SESSION
  session_unset();
  session_start();
  $_SESSION[$app]['GET']= array();

//   // ošetření běhu s testovací databází DEPRECATED
//   $tabu_db= '';
  if ( isset($_GET['test']) && $_GET['test'] ) {
    die("POZOR: prace s testovaci databazi byla zmenena (Martin vysvetli) ");
//     $title_style.= 'background-color:#ffffaa';
//     $app_name.= " ! TEST";
//     $_SESSION[$app]['GET']['test']= 1;
//     $tabu_db= $db_name;          // $db_name.= '_test'; v answer_ini
  }

  // ošetření běhu s testovací databází
  if ( substr($app,-5)=='_test' ) {
    $title_style.= 'background-color:#ffffaa';
    $app_name.= " ! TEST";
  }

  $title_style= $title_style ? " style='$title_style'" : '';

  // cesty
  $path= $_SERVER['PHP_SELF'];
  $path= substr($path,0,strrpos($path,'/'));
  $path= substr($path,0,strrpos($path,'/'));
  $abs_root= $_SERVER['DOCUMENT_ROOT'].$path;
  $rel_root= $_SERVER['HTTP_HOST'].$path;
  $_SESSION[$app]['app_path']= $path;

  set_include_path(get_include_path().PATH_SEPARATOR.$abs_root);

  require_once("$app.inc");
  require_once("{$EZER->version}/server/ae_slib.php");

  $client= "{$EZER->version}/client";
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
      "$client/ezer_report.js","$client/ezer_fdom2.js","$client/app.js",
      "$licensed/zeroclipboard/ZeroClipboard.js","$licensed/mootree.js"),
    // debugger
    $dbg ? array("$licensed/jush/mini_jush.js"):array(),
    // další knihovny
    array("$licensed/glfx.js"),
    // rozhodnout zda používat online mapy
    $gmap ? array("http://maps.googleapis.com/maps/api/js?sensor=false") : array(),
    // skripty pro Answer
    array("db/db_fce.js"),
    // uživatelské skripty
    $js_lib
  );

  $css= array_merge(
    $dbg ? array("./$licensed/jush/mini_jush.css") : array(),
    array(
      "./$client/ezer.css.php",
      "./$client/licensed/font-awesome/css/font-awesome.min.css",
      "./db/db.css.php"),
    $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker/datepicker_vista/datepicker_vista.css"):array(),
    // uživatelské css
    $css_lib,
    // css pro dotykové klienty
    $android ? array("$client/android.css") : array(),
    $ipad ? array("$client/ipad.css") : array()
  );

  global $answer_db;

  // doplnění Ezer.options a $EZER->options
  $options->awesome=    $awesome;        // zda použít v elementech ikony awesome fontu
  $options->answer_db=  "'$answer_db'";  // hlavní pracovní databáze
  $options->curr_version= 0;             // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
  $options->group_db=   "'ezer_answer'"; // databáze se společnými údaji pro skupinu aplikací Answer
  $options->path_files= "'$path_files'"; // absolutní cesta do složky files/{root}

  $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
        na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>{$EZER->options->mail}</a> "
      . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
      . ($EZER->options->skype ? "nebo použijte Skype&nbsp;<a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
      . "<br/><br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
  $menu= "<button id='android_menu' class='fa'><i class='fa fa-bars'></i></button>";
  $pars= (object)array(
//     'no_local' => true,                // true = nezohledňovat lokální přístup pro watch_key,watch_ip
    'dbg' => $dbg,                     // true = povolit podokno debuggeru v trasování
    'watch_key' => !$ezer_ksweb,       // true = povolit přístup jen po vložení klíče
    'watch_ip' => !$ezer_ksweb,        // true = povolit přístup jen ze známých IP adres
    'title_right' => "<span$title_style>$app_name</span>" . ($android || $ipad ? $menu : ""),
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
  root_php($app,$app_name,'chngs',$skin,$options,$js,$css,$pars,null,false);
}

?>
