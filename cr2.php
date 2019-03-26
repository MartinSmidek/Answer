<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $app_js     = pole s *.js
  #   $app_css    = pole s *.css
  #   $options    = doplnění Ezer.options
  #   $add_pars   = doplnění $EZER->options

  $kernel= "ezer3.1"; 
  $ezer_server= 
    $_SERVER["SERVER_NAME"]=='answer.bean'    ? 0 : (        // 0:lokální 
    $_SERVER["SERVER_NAME"]=='xxx.setkani.org' ? 1 : (       // 1:Lukáš - dead
    $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 2 : -1));// 2:Synology

  // parametry aplikace Answer/db2
  $app_name=  "Answer";
  $app= $app_root=  'cr2';

  $title_style= $ezer_server ? '' : "style='color:#ef7f13'";
  $title_flag=  $ezer_server ? '' : 'lokální ';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';
  // pro ezer2.2 nutno upravit ezer_main, ezer_ajax, ae_slib
  $kernel=   "ezer".(isset($_GET['ezer'])?$_GET['ezer']:'3.1'); 

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
      "http://answer.setkani.org");
  $rel_root= $rel_roots[$ezer_server];
  
  // upozornění na testovací verzi
  $demo= '';
  if ( $ezer_server==2 ) {
    $click= "jQuery('#DEMO').fadeOut(1000).delay(2000).fadeIn(1000);";
    $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
        . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
        . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
    $demo= "<div id='DEMO' onclick=\"$click\" style='$dstyle'><u>ostrá</u> verze</div>";
  }

  // skin a css
  $cookie= 4;
  $app_last_access= "{$app}_last_access";

  $k= substr($kernel,4,1)=='3' ? '3' : '';
  $choice_css= "skins/ck/ck.ezer$k.css=skin";

  $app_js= array("/db2/ds_fce$k.js","/db2/db2_fce$k.js");
  
  $app_css= [ 
      $choice_css,
      "/db2/db2.css",
//      $kernel=='ezer3.1' ? "$rel_root/db2/db2.css.php?skin" : "$rel_root/db2/db2.css",
      "/$kernel/client/wiki.css"
   ];

  //  require_once("answer.php");
  $options= (object) [
//    'watch_access' => 4,
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{4:'CPR Ostrava'},
         abbr:{4:'C'},
         css:{4:'ezer_ys'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0
  ];

  // (re)definice Ezer.options
  $kk= $k ?: '2';
  $add_pars= array(
    'favicon' => array("cr{$kk}_local.png","cr{$kk}.png","cr{$kk}_dsm.png")[$ezer_server],
//    'app_root' => "$rel_root",      // startovní soubory app.php a app.inc.php jsou v kořenu
//    'dbg' => $dbg,      // true = povolit podokno debuggeru v trasování a okno se zdrojovými texty
    'watch_key' => 1,   // true = povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = povolit přístup jen ze známých IP adres
    'title_right' => $app_name,
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
?>
