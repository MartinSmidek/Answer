<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 použije se databáze s postfixem _test
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  $kernel= "ezer3.1"; 
  $ezer_server= 
    $_SERVER["SERVER_NAME"]=='answer.bean'    ? 0 : (        // 0:lokální 
    $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 1 : (    // x:ostrý server
    $_SERVER["SERVER_NAME"]=='ans.setkani.org' ? 2 : -1));

  // parametry aplikace Answer/db2
  $app_name=  "Answer";
  $app= $app_root=  'db2';

  $title_style= $ezer_server ? '' : 'color:#ef7f13;';
  $title_flag=  $ezer_server ? '' : 'lokální ';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4';
  $dbg=      isset($_GET['dbg']) ? $_GET['dbg'] : 0;
  $gmap=     isset($_GET['gmap']) ? $_GET['gmap'] : $ezer_server;
  $verze=    '3.1';
  
//  echo("db2.php start, server=$ezer_server\n");

  // zrušení parametru &db_test
  if ( isset($_GET['db_test']) ) {
    die("parametr <b>db_test</b> byl dne 4.1.2019 nahrazen aplikaci dbt ...");
  }

  // cesty
  $abs_roots= array("C:/Ezer/beans/answer","/home/www/ezer/www-ys/2","/var/services/web/www/answer");
  $rel_roots= array("http://answer.bean:8080","https://answer.setkani.org","http://ans.setkani.org");
  $rel_root= $rel_roots[$ezer_server];
  
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
  $v= "<sub>3.1</sub>";
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

  $app_js= array("/db2/ds_fce.js","/db2/db2_fce3.js");
  
  $app_css= [ // $choice_css,
//      "/db2/db2.css",
      "$rel_root/db2/db2.css.php=skin",
      "/ezer3.1/client/wiki.css"
   ];

  //  require_once("answer.php");
  $options= (object) ['watch_access' => 3,
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
    'favicon' => array('db3_local.png','db3.png','db3_dsm.png')[$ezer_server],
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
//  echo("db2.php end<br>");
  // je to aplikace se startem v kořenu
  require_once("ezer3.1/ezer_main.php");

?>
