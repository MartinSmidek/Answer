<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  // časová značka při spuštění
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
  $ezer_version= isset($_GET['ezer']) ? $_GET['ezer'] : '3.2'; 

  // server, databáze, cesty, klíče
  $deep_root= "../files/answer";
  require_once("$deep_root/dbt.dbs.php");

  // parametry aplikace Answer/dbt
  $test= '-TEST';
  $app_name=  "Ansver$test";
  $ezer_root= $app= $app_root=  'dbt';

  $title_style= $ezer_server==1 ? "style='color:#0094FF'" : (
                $ezer_server==0 ? "style='color:#ef7f13'" : '');
  $title_flag=  in_array($ezer_server,[0,4]) ? 'lokální ' : '';
  $jirka_flag= strpos($abs_root,'jirka-') ? " Jirka " : '';

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

  // upozornění na testovací verzi
  $demo= '';
  // zmizí a zase se objeví
  $click= "jQuery('#DEMO').fadeOut(200).delay(5000).fadeIn(1000);";
  // jen zmizí
  //$click= "jQuery('#DEMO').fadeOut(500);";
  // nezmizí
//  $click= '';
  
  $dstyle= "left:0; top:0; position:fixed; transform:rotate(320deg) translate(-128px,-20px); "
      . "width:500px;height:100px;background:orange; color:white; font-weight: bolder; "
      . "text-align: center; font-size: 40px; line-height: 96px; z-index: 16; opacity: .5;";
  $demo= "<div id='DEMO' onmouseover=\"$click\" style='$dstyle'>testovací data</div>";

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
  $new= $ezer_version!='3.1' 
      ? '<sub><small> '.$ezer_version.($touch?' touch':'').'</small></sub>' : '';
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
    $cookie==2 ? "default" : "default" );

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
