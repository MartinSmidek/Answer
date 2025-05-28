<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  // časová značka při spuštění
  date_default_timezone_set("Europe/Prague");
  file_put_contents("last_access.txt",date('Y-m-d H:i:s'));

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... ms50
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze 
  #   $skin       = tt|default|default
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  // verze použitého jádra Ezeru
  $ezer_version= '3.2'; 

  // server, databáze, cesty, klíče
  $deep_root= "../files/answer";
  require_once("$deep_root/ms50_test.dbs.php");

  // parametry aplikace Answer/ms50
  $test= '-TEST';
  $app_name=  "Answer$test";
  $ezer_root= $app= $app_root=  'ms50';
  
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
    . "$app_name$new  MS50+"
    ."</span>";
  $skin= "tt";

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");

  $app_css= [ 
      "db2/db2.css.php=skin",
      "ezer$ezer_version/client/wiki.css"
   ];
  //  require_once("answer.php");
  $add_options= (object) [
    'watch_git'    => 1,                // sleduj git-verzi aplikace a jádra, při změně navrhni restart
    'curr_version' => 1,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'watch_access' => 16,               // 16
    'path_akce'    => "'$path_akce'", // absolutní cesta do složky Akce
    'path_foto'    => "'$path_foto'", // absolutní cesta do složky fotky
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{16:'MS50+'},
         abbr:{16:'M'},
         css:{16:'ezer_fa'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'e'",
    'autoskill'    => "'!e'",
    'db_test'      => 0
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
