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
  $skin= 'ck';

  $title_style= $ezer_server ? '' : "style='color:#ef7f13'";
  $title_flag=  $ezer_server ? '' : 'lokální ';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';
  // pro ezer2.2 nutno upravit ezer_main, ezer_ajax, ae_slib
  $kernel=   "ezer".(isset($_GET['ezer'])?$_GET['ezer']:'3.1'); 

  // nastav jako default PDO=2
  if ( !isset($_GET['pdo']))
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
  
  // upozornění na testovací verzi
  $demo= '';
  if ( $ezer_server==2 ) {
    $click= "jQuery('#DEMO').fadeOut(500);";
    $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
        . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
        . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
    $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>nový server</div>";
  }

  // skin a css
  $cookie= 4;
  $app_last_access= "{$app}_last_access";

  $k= substr($kernel,4,1)=='3' ? '3' : '';
  $app_js= array("/db2/ds_fce$k.js","/db2/db2_fce$k.js");
  
  $app_css= [ 
      "db2/db2.css.php=skin",
      "/$kernel/client/wiki.css"
   ];

  //  require_once("answer.php");
  $add_options= (object) [
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
  $title= "$demo$app_name<sub>$k</sub>";
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
?>
