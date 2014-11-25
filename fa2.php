<?php # Systém An(w)er/Ezer2, (c) 2008-2010 Martin Šmídek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ezer_ksweb= $android && $_SERVER["SERVER_NAME"]=="localhost"; // identifikace ladícího serveru KSWEB/Android

  $app=      'fa2';             // jméno adresáře a hlavního objektu aplikace == $ezer_root!
  $app_name= 'Ans(w)er - Familia'.($ezer_ksweb?" / test Android":"");;
  $skin=     'default';
  $skin=     'ch';
  $title_style= $ezer_local ? 'color:#ef7f13;' : '';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4';
  $dbg=      isset($_GET['dbg']) ? $_GET['dbg'] : 0;
 $gmap=     isset($_GET['gmap']) ? true : !($ezer_local || $ezer_ksweb);
  $awesome=  isset($_GET['awesome']) ? $_GET['awesome'] : 3;

  // ošetření běhu s testovací databází
  $_SESSION[$app]['GET']['test']= 0;
  if ( isset($_GET['test']) && $_GET['test'] ) {
    $title_style.= 'background-color:#ffffaa';
    $app_name.= " ! TEST";
    $_SESSION[$app]['GET']['test']= 1;
  }
  $title_style= $title_style ? " style='$title_style'" : '';

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
    // pro Android
    $android
    ? array("$licensed/Mslider.js","$licensed/Mdrag.js") : array(),
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
    // uživatelské skripty
    array("db/db_fce.js","ds/fce.js")
  );

  $css= array_merge(
    $dbg ? array("./$licensed/jush/mini_jush.css") : array(),
    array(
      "./$client/ezer.css.php",
      "./$client/licensed/font-awesome/css/font-awesome.min.css",
      "./fa/fa.css.php",
      "./db/db.css.php"),
    /* pro verzi 2.2 */ $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker/datepicker_vista/datepicker_vista.css"):array()
  );

  global $answer_db;
  $options= (object)array(
    'awesome'    => $awesome,           // zda použít v elementech ikony awesome fontu
    'skill'      => "'f'",
    'autoskill'  => "'!f'",
    'answer_db'  => "'$answer_db'"
  );
  $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
        na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>{$EZER->options->mail}</a> "
      . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
      . ($EZER->options->skype ? "nebo použijte Skype&nbsp;<a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
      . "<br/><br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
  $pars= (object)array(
//     'no_local' => true,                // true = nezohledňovat lokální přístup pro watch_key,watch_ip
    'dbg' => $dbg,                     // true = povolit podokno debuggeru v trasování
    'watch_key' => !$ezer_ksweb,       // true = povolit přístup jen po vložení klíče
    'watch_ip' => !$ezer_ksweb,        // true = povolit přístup jen ze známých IP adres
    'title_right' => "<span$title_style>$app_name</span>"
                     . ($android ? "<button id='android_menu'><i class='fa fa-bars'></i></button>" : ""),
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
  root_php($app,$app_name,'news',$skin,$options,$js,$css,$pars);

?>