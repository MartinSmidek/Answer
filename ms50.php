<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $app_js     = pole s *.js
  #   $app_css    = pole s *.css
  #   $options    = doplnění Ezer.options
  #   $add_pars   = doplnění $EZER->options

  $kernel= "ezer3.2"; 
  $ezer_version= '3.2'; 
  $ezer_server= 
    $_SERVER["SERVER_NAME"]=='answer.bean'        ? 0 : (      // 0:lokální 
    $_SERVER["SERVER_NAME"]=='answer.doma'        ? 1 : (      // 1:Synology DOMA
    $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 2 : -1));  // 2:Synology YMCA

  // parametry aplikace Answer/db2
  $app_name=  "Answer";
  $app= $app_root=  'ms50';
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
      "",
      "/volume1/web/www/answer");
//      "/var/services/web/www/answer");
  $rel_roots= array(
      "http://answer.bean:8080",
      "",
      "https://answer.setkani.org");
  $rel_root= $rel_roots[$ezer_server];
  
  // upozornění na testovací verzi
  $demo= '';
//  if ( $ezer_server==2 ) {
//    $click= "jQuery('#DEMO').fadeOut(500);";
//    $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
//        . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
//        . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
//    $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>demo data</div>";
//  }

  // skin a css
  $cookie= 16;
  $app_last_access= "ms_last_access";

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");
  
  $app_css= [ 
      "db2/db2.css.php=skin",
      "$kernel/client/wiki.css"
   ];

  //  require_once("answer.php");
  $add_options= (object) [
    'watch_access' => 16,
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{16:'MS50+'},
         abbr:{16:'M'},
         css:{16:'ezer_ms'}}",
    'web'          => "'http://www.centrumrodin.cz/pro-vas/pro-manzele/manzelska-setkani'", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0,
    'dbg'          => "{path:['db2','ms50']}"
  ];

  // (re)definice Ezer.options
  $title= "$demo<span $title_style>$title_flag$app_name<sub>3.2</sub> MS50+</span>";
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
  require_once("$kernel/ezer_main.php");

