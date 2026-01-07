<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  // časová značka při spuštění
  date_default_timezone_set("Europe/Prague");
  file_put_contents("last_access.txt",date('Y-m-d H:i:s'));

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... dbt
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 použije se databáze s postfixem _test
  #   $skin       = tt|default|default
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  // verze použitého jádra Ezeru
  $ezer_version= isset($_GET['ezer']) ? $_GET['ezer'] : '3.3'; 

  // server, databáze, cesty, klíče
  $deep_root= "../files/answer";
  require_once("$deep_root/dbt.dbs.php");

  // parametry aplikace Answer/dbt
  $test= '-TEST';
  $app_name=  "Ansver$test";
  $ezer_root= $app= $app_root=  'dbt';
  
  // nastav jako default PDO=2
  if ( !isset($_GET['pdo']))
    $_GET['pdo']= 2;
  
  if ( isset($_GET['batch']) && $_GET['batch'] ) {
    // ochrana volání
    $secret= "WEBPOUZEAUTORIZOVANEVOLANIKEYWEB";
    if ( count($_POST) && !isset($_POST['post']) ) {
      $x= array2object($_POST);
      // ochrana heslem
      if ( $_POST['secret']!==$secret ) { echo "?"; exit; }
      $y= $x;
      server($x);
      header('Content-type: application/json; charset=UTF-8');
      $yjson= json_encode($y);
      echo $yjson;
      exit;
    }
    elseif ( !isset($_GET['secret']) || $_GET['secret']!=$secret ) {
      // ochrana heslem
      echo "?";
      exit;
    }
    // ok - batch
    session_start();
    $_SESSION[$ezer_root]['ezer_server']= $ezer_server;
    $_SESSION[$ezer_root]['ezer']= $ezer_version;
    $_SESSION[$ezer_root]['abs_root']= $abs_root; //s[$ezer_server];
    $_SESSION[$ezer_root]['rel_root']= $rel_root; //s[$ezer_server];
    $_SESSION[$ezer_root]['pdo']= $_GET['pdo'];
    $_POST['root']= $ezer_root;
    require_once("$app_root.inc.php");
    $html= '';
    try {
      error_reporting(0);
      switch ($_GET['batch']) {
      case 'fio-get':
        foreach (['load-ys'=>'LOAD YS','join-ys'=>'AKCE YS',
                  'load-ds'=>'LOAD DS','join-ds'=>'AKCE DS'] as $fce=>$note) {
          $y= ds2_fio((object)['fce'=>$fce,'od'=>'*','do'=>'*']);
          $html.= "<br>\n$note: $y->html"; 
        }
        break;
      }
    } 
    catch (Throwable $e) { 
      echo "<br>po catch";
      $html.= "<hr>\nERROR: ".$e->getMessage(); 
    }
    $last_run= "BATCH {$_GET['batch']} started ".date('j.n.Y H:i:s')."$html";
    echo "<br>$last_run";
    $_SESSION[$ezer_root]['last_batch']= $last_run;
    echo "<br>save into $abs_root/last_batch.txt";
    file_put_contents("$abs_root/last_batch.txt",$last_run);
    exit();
  }  

  $title_style= $ezer_server==1 ? "style='color:#0094FF'" : (
                $ezer_server==0 ? "style='color:#ef7f13'" : '');
  $title_flag=  in_array($ezer_server,[0,4]) ? 'lokální ' : '';
  $jirka_flag= strpos($abs_root,'jirka-') ? " Jirka " : '';

  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4.6';
  
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

  // upozornění na testovací verzi
  $demo= '';
  // zmizí a zase se objeví
//  $click= "jQuery('#DEMO').fadeOut(200).delay(200000).fadeIn(1000);";
  // jen zmizí
  $click= "jQuery('#DEMO').fadeOut(500);";
  // nezmizí
//  $click= '';
  
//  $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
//      . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
//      . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
  $dstyle= "left:-50px; bottom:0; position:fixed; transform:rotate(40deg) translate(-107px,-14px); "
      . "width:500px;height:80px;background:orange; color:white; font-weight: bolder; "
      . "text-align: center; font-size: 40px; line-height: 75px; z-index: 16; opacity: .5;";
  $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>testovací data</div>";
//  $demo= "<div id='DEMO' style='$dstyle'>testovací data</div>";

  // skin a css
  $cookie= 3;
  $app_last_access= "{$app}_last_access";
  if ( isset($_COOKIE[$app_last_access])
    && $_COOKIE[$app_last_access]>0 &&  $_COOKIE[$app_last_access]<4 ) {
    $cookie= $_COOKIE[$app_last_access];
  }
  $access_app= array(1=>"Setkání","Familia","(společný)");
  $access_app= $access_app[$cookie];
  $choice_js= "personify('menu_on','$test'); return false;";
  // doplnění jména aplikace o laděnou verzi ezer
  $new= '<sub><small> '.$ezer_version.($touch?' touch':'').'</small></sub>';
  $title= "$demo
    <span $title_style>"
    . $title_flag
    ."<span id='access' onclick=\"$choice_js\" oncontextmenu=\"$choice_js\">
        $app_name$new $access_app
      </span>
      <div id='access_menu'>
        <span onclick=\"personify(1,'$test');\">Answer Setkání</span>
        <span onclick=\"personify(2,'$test');\">Answer Familia</span>
        <span onclick=\"personify(3,'$test');\" class='separator'>Answer (společný)</span>
      </div>
    </span>";
  
  $coo= filter_input(INPUT_COOKIE,$app_last_access,FILTER_VALIDATE_INT);
  if ( $coo && $coo>0 && $coo<4 ) {
    $cookie= $coo;
  }
  $skin=
    $cookie==1 ? "tt" : (
    $cookie==2 ? "tt" : "default" );

  $app_js= array("db2/ds_fce3.js","db2/db2_fce3.js");

  $app_css= [ 
      "db2/db2.css.php=skin",
      "ezer$ezer_version/client/wiki.css"
   ];
  //  require_once("answer.php");
  $add_options= (object) [
    'watch_git'    => 1,                // sleduj git-verzi aplikace a jádra, při změně navrhni restart
    'curr_version' => 1,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'watch_access' => 67,               // 1 + 2 + 64
    'path_akce'    => "'$path_akce'", // absolutní cesta do složky Akce
    'path_foto'    => "'$path_foto'", // absolutní cesta do složky fotky
    'group_db'     => "'ezer_answer'",
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{1:'Setkání',2:'Familia',3:'Setkání+Familia',65:'S+DS',66:'F+DS',67:'S+F+DS'},
         abbr:{1:'S',2:'F',3:'S+F',65:'S+DS',66:'F+DS',67:'S+F+DS'},
         css:{1:'ezer_ys',2:'ezer_fa',3:'ezer_db',65:'ezer_ys ezer_ds',66:'ezer_fa ezer_ds',67:'ezer_db ezer_ds'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0
  ];

  // (re)definice Ezer.options
  $add_pars= array(
    'login_interval' => 120, // minuty povolené nečinosti
    'favicon' => $favicon,
    'watch_pin' => 1,   // true = povolit dvoufázové přihlašování pomocí _user.usermail a PINu
    'watch_key' => 1,   // true = nebo povolit přístup jen po vložení klíče
    'watch_ip' => 1,    // true = jinak povolit přístup jen ze známých IP adres
    'title_right' => $jirka_flag.$title,
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
  $app_name= $jirka_flag ? "$jirka_flag Answer" : $app_name;
  // je to standardní aplikace se startem v kořenu
  require_once("ezer$ezer_version/ezer_main.php");
