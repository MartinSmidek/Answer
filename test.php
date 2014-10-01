<?php

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $app=      'test';
  $app_name= 'Test/Answer';
  $skin=     'ck';
  $CKEditor= $_GET['editor'] ? $_GET['editor'] : '';
  $dbg=      $_GET['dbg'] ? 1 : 0;                                                    /* debugger */

  require_once("$app.inc");
  require_once("{$EZER->version}/server/ae_slib.php");

  $client= "{$EZER->version}/client";
  $app_name.= substr($EZER->version,4);
  $licensed= "$client/licensed";
  $js= array_merge(
    // ckeditor a mootools
    array("$licensed/ckeditor$CKEditor/ckeditor.js","$licensed/clientcide.js"),
    // pro verzi 2.1
    $EZER->version=='ezer2'
    ? array("$licensed/mootools/asset.js","$licensed/mootools/slider.js"):array(),
    // pro verzi 2.2
    $EZER->version=='ezer2.2'
    ? array("$licensed/datepicker.js"):array(),
    // jádro Ezer
    array("$client/lib.js","$client/ezer_fdom1.js","$client/ezer.js","$client/area.js",
      "$client/ezer_report.js","$client/ezer_fdom2.js","$client/app.js","$client/lib.js"),
    // debugger                                                                       /* debugger */
    $dbg ? array("$licensed/jush/mini_jush.js"):array(),                              /* debugger */
    // pluginy
    array("$licensed/zeroclipboard/ZeroClipboard.js","$licensed/mootree.js"),
    // uživatelské skripty - v lokálu bez síťových přístupů
    $ezer_local
    ? array("test/fce.js","test/test_google.js")
    : array("test/fce.js","test/test_google.js",
        "http://api4.mapy.cz/loader.js",
        "http://maps.googleapis.com/maps/api/js?libraries=geometry&sensor=false")
  );
  $css= array(
    $dbg ? "./$licensed/jush/mini_jush.css" : '',                                     /* debugger */
    "./$client/ezer.css.php",
    "./$client/licensed/datepicker/datepicker_vista/datepicker_vista.css",
    "./$client/licensed/font-awesome/css/font-awesome.min.css",
    "./test/test.css"
  );
  $options= (object)array(
//     'path_docs'      => "'$ezer_path_docs'",  // defaultní hodnota, kterou není nutné uvádět
    'group_login'    => "'test,fis,klub'",
    'login_interval' => 21*60                 // povolená nečinnost v minutách - 21 hodiny
//     'skill'      => "'f'",
//     'autoskill'  => "''",
  );
  $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
        na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>{$EZER->options->mail}</a> "
      . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
      . ($EZER->options->skype ? "nebo použijte Skype&nbsp;<a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
      . "<br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
  $pars= (object)array(
    'dbg' => $dbg,                                                                    /* debugger */
    'watch_ip' => false,
    'watch_key' => true,
    'title_right' => $ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name,
    'contact' => $kontakt,
    'gc_maxlifetime'    => 12*60*60,            // životnost SESSION v sekundách - 12 hodin
    'CKEditor' => "{
      version:'$CKEditor',
      Minimal:{toolbar:[['Bold','Italic','Source']]},
      IntranetSlim:{
        toolbar:[['Bold','Italic','-','Link','Unlink','-','Source']],
        removePlugins:'wsc,elementspath,scayt'
      },
      EzerHelp2:{
        toolbar:[['PasteFromWord','-','Bold','Italic','TextColor','BGColor',
        '-','JustifyLeft','JustifyCenter','JustifyRight',
        '-','Link','Unlink','HorizontalRule','Image',
        '-','NumberedList','BulletedList',
        '-','Outdent','Indent',
        '-','Source','ShowBlocks','RemoveFormat']]
        }
      }"
  );
  $const= (object)array();
//   $const->fifty= 100/3;
  $const->const_rows= 7;
  $const->const_left= 200;
  $const->const_width= 200;
  root_php($app,$app_name,'test',$skin,$options,$js,$css,$pars,$const);

?>
