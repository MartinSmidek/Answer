<?php # Systém An(w)er/Ezer2, (c) 2008-2010 Martin Šmídek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru
  $ezer_ksweb= $_SERVER["SERVER_NAME"]=="localhost"; // identifikace ladícího serveru KSWEB/Android

  $app=      'ys';
  $app_name= 'Ans(w)er'.($ezer_ksweb?" / test Android":"");;
  $skin=     'default';
  $skin=     'ck';
  $CKEditor= isset($_GET['editor']) ? $_GET['editor'] : '4';
  $dbg=      $_GET['dbg'];
  $gmap=     isset($_GET['gmap']) ? true : !($ezer_local || $ezer_ksweb);

  require_once("$app.inc");
  require_once("{$EZER->version}/server/ae_slib.php");
  $app_name.= $EZER->options->mysql ? " - {$EZER->options->mysql}" : '';

  $client= "{$EZER->version}/client";
  $licensed= "$client/licensed";
  $js= array_merge(
    // ckeditor a mootools
    array("$licensed/ckeditor$CKEditor/ckeditor.js","$licensed/clientcide.js"),
    // pro verzi 2.1
    $EZER->version=='ezer2'
    ? array("$licensed/mootools/asset.js","$licensed/mootools/slider.js"):array(),
    // pro Android
    $ezer_ksweb ? array("$licensed/Mslider.js","$licensed/Mdrag.js") : array(),
    // pro verzi 2.2
    $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker.js"):array(),
    // jádro Ezer
    array("$client/lib.js","$client/ezer_fdom1.js","$client/ezer.js","$client/ezer_report.js",
      "$client/ezer_fdom2.js","$client/app.js","$licensed/zeroclipboard/ZeroClipboard.js"),
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
    array("./$client/ezer.css.php","./ys/ys.css.php","./db/db.css.php"),
    /* pro verzi 2.2 */ $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker/datepicker_vista/datepicker_vista.css"):array()
  );

  $options= (object)array(
    'skill'      => "'y'",
    'autoskill'  => "'!y'",
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
    'title_right' => $ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name,
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
