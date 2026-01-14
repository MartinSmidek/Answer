<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  // časová značka při spuštění
  date_default_timezone_set("Europe/Prague");
  file_put_contents("last_access.txt",date('Y-m-d H:i:s'));

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... ms2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze 
  #   $skin       = tt|default|default
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  // verze použitého jádra Ezeru
  $ezer_version= isset($_GET['ezer']) ? $_GET['ezer'] : '3.3'; 
  $test= '';

  // server, databáze, cesty, klíče
  $deep_root= "../files/answer";
  require_once($test?"$deep_root/ms2t.dbs.php":"$deep_root/ms2.dbs.php");

  // parametry aplikace Answer/ms2
  $app_name=  "Answer$test";
  $ezer_root= $app= $app_root=  'ms2'.($test?'t':'');
  
  // nastav jako default PDO=2
  $_GET['pdo']= 2;
  
  $title_style= $ezer_server==1 ? "style='color:#0094FF'" : (
                $ezer_server==0 ? "style='color:#ef7f13'" : '');
  $title_flag=  in_array($ezer_server,[0,4]) ? 'lokální ' : '';

  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';
  
  // nastav &touch=0 pro Windows
  if (strtoupper(substr(PHP_OS,0,3))==='WIN') 
    $_GET['touch']= 0;
  $touch= isset($_GET['touch']) ? $_GET['touch'] : 0;

  // ochránění přímého přístupu do složek s .htaccess/RewriteCond "%{HTTP_COOKIE}" "!EZER"
  setcookie("EZER",$app,0,"/");

  // upozornění na testovací verzi
  $click= "jQuery('#DEMO').fadeOut(500);";
  $dstyle= "left:-50px; bottom:0; position:fixed; transform:rotate(40deg) translate(-107px,-14px); "
      . "width:500px;height:80px;background:orange; color:white; font-weight: bolder; "
      . "text-align: center; font-size: 40px; line-height: 75px; z-index: 16; opacity: .5;";
  $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>testovací data</div>";

  // skin a css
  $new= '<sub><small> '.$ezer_version.($touch?' touch':'').'</small></sub>';
  $title= "$demo
    <span $title_style>"
    . $title_flag
    . "$app_name$new  MS"
    ."</span>";
  $skin= $test ? 'tt' : 'ch';

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");

  $app_css= [ 
      "db2/db2.css.php=skin",
      "ezer$ezer_version/client/wiki.css"
   ];

  $add_options= (object) [
    'watch_git'    => 1,                // sleduj git-verzi aplikace a jádra, při změně navrhni restart
    'curr_version' => 1,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'watch_access' => 8,                // 8
    'path_akce'    => "'$path_akce'", // absolutní cesta do složky Akce
    'path_foto'    => "'$path_foto'", // absolutní cesta do složky fotky
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{8:'Šance pro manželství z.s.'},
         abbr:{8:'M'},
         css:{8:'ezer_ms'}}",
    'web'          => "'manzelskasetkani.cz'", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0,
  ];

  // (re)definice Ezer.options
  $add_pars= array(
    'favicon' => $favicon,
    'watch_pin' => 1,   // true = povolit dvoufázové přihlašování pomocí _user.usermail a PINu
    'watch_key' => 1,   // true = nebo povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = jinak povolit přístup jen ze známých IP adres
    'title_right' => $title,
//    'contact' => $kontakt,
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
  // je to standardní aplikace se startem v kořenu
  require_once("ezer$ezer_version/ezer_main.php");

/* OLD ms2.php
  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $app_js     = pole s *.js
  #   $app_css    = pole s *.css
  #   $options    = doplnění Ezer.options
  #   $add_pars   = doplnění $EZER->options

  // verze použitého jádra Ezeru
  $ezer_version= isset($_GET['ezer']) ? $_GET['ezer'] : '3.2'; 

  $ezer_server= 
    $_SERVER["SERVER_NAME"]=='answer.bean'        ? 0 : (      // 0:lokální 
    $_SERVER["SERVER_NAME"]=='answer.doma'        ? 1 : (      // 1:Synology DOMA
    $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 2 : -1));  // 2:Synology YMCA

  // parametry aplikace Answer/db2
  $app_name=  "Answer";
  $app= $app_root=  'ms2';
  $app_version_in= 'db2';
  $skin= 'ch';

  $title_style= $ezer_server ? '' : "style='color:#ef7f13'";
  $title_flag=  $ezer_server==2 ? '' : 'lokální ';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';

  // nastav PDO=2
  $_GET['pdo']= 2;

  // ochránění přímého přístupu do složek s .htaccess/RewriteCond "%{HTTP_COOKIE}" "!EZER"
  setcookie("EZER",$app,0,"/");

  // cesty
  $abs_roots= array(
      "C:/Ezer/beans/answer",
      "/home/www/ezer/www-ys/2",
      "/volume1/web/www/answer");
//      "/var/services/web/www/answer");
  $rel_roots= array(
      "http://answer.bean:8080",
      "https://answer.setkani.org",
      "https://answer.setkani.org");
  $rel_root= $rel_roots[$ezer_server];
  
//  // upozornění na testovací verzi
//  $demo= '';
//  if ( $ezer_server==2 ) {
//    $click= "jQuery('#DEMO').fadeOut(500);";
//    $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
//        . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
//        . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
//    $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>demo data</div>";
//  }

  // skin a css
  $cookie= 8;
  $app_last_access= "ms_last_access";

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");
  
  $app_css= [ 
      "db2/db2.css",
      "db2/db2.css.php=skin",
      "ezer$ezer_version/client/wiki.css"
   ];

  //  require_once("answer.php");
  $add_options= (object) [
    'watch_access' => 8,
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{8:'Šance pro manželství z.s.'},
         abbr:{8:'M'},
         css:{8:'ezer_ms'}}",
    'web'          => "'manzelskasetkani.cz'", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0,
    'dbg'          => "{path:['db2','ms2']}"
  ];

  // (re)definice Ezer.options
  $title= "<span $title_style>$title_flag$app_name<sub>3.2</sub> Šance pro manželství</span>";
  $add_pars= array(
    'favicon' => array("{$app}_local.png","{$app}.png","{$app}_dsm.png")[$ezer_server],
//    'app_root' => "$rel_root",      // startovní soubory app.php a app.inc.php jsou v kořenu
//    'dbg' => $dbg,      // true = povolit podokno debuggeru v trasování a okno se zdrojovými texty
    'watch_key' => 1,   // true = povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = povolit přístup jen ze známých IP adres
    'title_right' => $title,
//    'contact' => $kontakt,
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

  // je to aplikace se startem v kořenu
  require_once("ezer$ezer_version/ezer_main.php");
*/
