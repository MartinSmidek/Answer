<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 použije se databáze s postfixem _test
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  // verze použitého jádra Ezeru
  $ezer_version= isset($_GET['ezer']) ? $_GET['ezer'] : '3.1'; 
  $ezer_server= 
  $_SERVER["SERVER_NAME"]=='answer.bean'        ? 0 : (      // 0:lokální 
  $_SERVER["SERVER_NAME"]=='answer.doma'        ? 1 : (      // 1:Synology DOMA
  $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 2 : (      // 2:Synology YMCA
  $_SERVER["SERVER_NAME"]=='192.168.7.111'      ? 2 : -1))); // 2:Synology YMCA (pro cron!!!) 

  // parametry aplikace Answer/db2
  $app_name=  "Answer";
  $app= $app_root=  'db2';

  $title_style= $ezer_server==1 ? "style='color:#0094FF'" : (
                $ezer_server==0 ? "style='color:#ef7f13'" : '');
  $title_flag=  $ezer_server==2 ? '' : 'lokální ';
  
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';
  
  // nastav jako default PDO=2
  if ( !isset($_GET['pdo']))
    $_GET['pdo']= 2;
  
  // nastav &touch=0 pro Windows
  if (strtoupper(substr(PHP_OS,0,3))==='WIN') 
    $_GET['touch']= 0;
  $touch= isset($_GET['touch']) ? $_GET['touch'] : 0;

  // ochránění přímého přístupu do složek s .htaccess/RewriteCond "%{HTTP_COOKIE}" "!EZER"
  setcookie("EZER",$app,0,"/");

  // zrušení parametru &db_test
  if ( isset($_GET['db_test']) ) {
    die("parametr <b>db_test</b> byl dne 4.1.2019 nahrazen aplikaci dbt ...");
  }

  // cesty
  $abs_roots= array(
      "C:/Ezer/beans/answer",
      "/volume1/web/www/answer",
      "/volume1/web/www/answer"
    );
  $rel_roots= array(
      "http://answer.bean:8080",
      "http://answer.doma",
      "https://answer.setkani.org"
    );
  $rel_root= $rel_roots[$ezer_server];
  $path_foto= "{$abs_roots[$ezer_server]}/fotky";
  $path_akce= array(
      "D:/MS/",
      "/volume1/YS/",
      "/volume1/YS/"
    )[$ezer_server];
  
  // upozornění na testovací verzi
  $demo= '';
//  if ( $ezer_server==2 ) {
//    // zmizí a zase se objeví
//    //$click= "jQuery('#DEMO').fadeOut(500).delay(300000).fadeIn(2000);";
//    // jen zmizí
//    $click= "jQuery('#DEMO').fadeOut(500);";
//    $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
//        . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
//        . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
//    $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>nový server</div>";
//  }

  // skin a css
  $cookie= 3;
  $app_last_access= "{$app}_last_access";
  if ( isset($_COOKIE[$app_last_access])
    && $_COOKIE[$app_last_access]>0 &&  $_COOKIE[$app_last_access]<4 ) {
    $cookie= $_COOKIE[$app_last_access];
  }
  $access_app= array(1=>"Setkání","Familia","(společný)");
  $access_app= $access_app[$cookie];
  $choice_js= "personify('menu_on'); return false;";
  // doplnění jména aplikace o laděnou verzi ezer
  $new= $ezer_version!='3.1' 
      ? '<sub><small> '.$ezer_version.($touch?' touch':'').'</small></sub>' : '';
  $title= "$demo
    <span $title_style>"
    . $title_flag
    ."<span id='access' onclick=\"$choice_js\" oncontextmenu=\"$choice_js\">
        Answer$new $access_app
      </span>
      <div id='access_menu'>
        <span onclick='personify(1);'>Ans(w)er Setkání</span>
        <span onclick='personify(2);'>Ans(w)er Familia</span>
        <span onclick='personify(3);' class='separator'>Ans(w)er (společný)</span>
      </div>
    </span>";
  
  $coo= filter_input(INPUT_COOKIE,$app_last_access,FILTER_VALIDATE_INT);
  if ( $coo && $coo>0 && $coo<4 ) {
    $cookie= $coo;
  }
//  $choice_css=
//    $cookie==1 ? "skins/ck/ck.ezer.css=skin" : (
//    $cookie==2 ? "skins/ch/ch.ezer.css=skin" 
//               : "skins/db/db.ezer.css=skin" );
  $skin=
    $cookie==1 ? "ck" : (
    $cookie==2 ? "ch" : "db" );

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");

  $app_css= [ 
      "db2/db2.css.php=skin",
      "ezer$ezer_version/client/wiki.css"
   ];
  //  require_once("answer.php");
  $add_options= (object) [
    'watch_git'    => 1,                // sleduj git-verzi aplikace a jádra, při změně navrhni restart
    'curr_version' => 1,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'watch_access' => 3,
    'path_akce'    => "'$path_akce'", // absolutní cesta do složky Akce
    'path_foto'    => "'$path_foto'", // absolutní cesta do složky fotky
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{1:'Setkání',2:'Familia',3:'Setkání+Familia'},
         abbr:{1:'S',2:'F',3:'S+F'},
         css:{1:'ezer_ys',2:'ezer_fa',3:'ezer_db'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0
  ];

  // (re)definice Ezer.options
  $add_pars= array(
    'favicon' => array("db3_local.png","db3.png","db3_dsm.png")[$ezer_server],
//    'app_root' => "$rel_root",      // startovní soubory app.php a app.inc.php jsou v kořenu
//    'dbg' => $dbg,      // true = povolit podokno debuggeru v trasování a okno se zdrojovými texty
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
  if ( isset($_GET['batch']) && $_GET['batch'] ) {
    // batch - verze
    error_reporting(E_ALL & ~E_NOTICE);
    session_start();
    echo($_SERVER["SERVER_NAME"].'<br>');
    $ezer_root= 'db2';
    // nastavení cest
    $abs_root= isset($ezer_server) ? $abs_roots[$ezer_server] : $abs_roots[$ezer_local];
    $_SESSION[$ezer_root]['ezer_server']= $ezer_server;
    $_SESSION[$ezer_root]['ezer']= "3.1";
    $_SESSION[$ezer_root]['abs_root']= $abs_root; 
    $_SESSION[$ezer_root]['rel_root']= $rel_root; 
    $_SESSION[$ezer_root]['pdo']= 2;
    $_POST['root']= $ezer_root;
    // inicializace Answeru
    require_once("db2.inc.php");
    switch ($_GET['batch']) {
    case 'kasa-mail':
      $stamp= kasa_send('*',1);
      echo "<hr><h2>Odeslání připomenutí</h2><br>$html";
      break;
    }
  }
  else {
    // je to standardní aplikace se startem v kořenu
    require_once("ezer$ezer_version/ezer_main.php");
  }

?>
