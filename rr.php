<?php

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $app=      'rr';
  $app_name= 'Myšlenky Richarda Rohra';
  $skin=     'default';

  require_once("$app.inc");
  require_once("{$EZER->version}/server/ae_slib.php");

  if ( $_GET['batch'] ) {
    // batch - verze
    switch ($_GET['batch']) {
    case 'rr-today':
      require_once("rr/rr_fce.php");
      $html= rr_send((object)array('den'=>'','poslat'=>1,'opakovat'=>0));
      echo "rr_send=$html";
      break;
    }
  }
  else {
    $client= "{$EZER->version}/client";
    $licensed= "$client/licensed";
    $js= array_merge(
      // ckeditor and mootools
      array(/*"$licensed/ckeditor/ckeditor.js",*/"$licensed/clientcide.js"),
      // pro verzi 2.1
      $EZER->version=='ezer2'
      ? array("$licensed/mootools/asset.js","$licensed/mootools/slider.js"):array(),
      // pro verzi 2.2
      $EZER->version=='ezer2.2'
      ? array("$licensed/datepicker.js"):array(),
      // jádro Ezer
      array("$client/lib.js","$client/ezer_fdom1.js","$client/ezer.js","$client/ezer_report.js",
        "$client/ezer_fdom2.js","$client/app.js","{$EZER->version}/client/lib.js",
        "$licensed/zeroclipboard/ZeroClipboard.js")
      // uživatelské skripty
    );
    $css= array_merge(
      array("./$client/ezer.css.php"),
      /* pro verzi 2.2 */ $EZER->version=='ezer2.2'
      ? array("$licensed/datepicker/datepicker_vista/datepicker_vista.css"):array()
    );
    $options= (object)array(
      'must_log_in'    => 'false',
      'user_record'    => 'null',
      'login_interval' => 2*60                 // povolená nečinnost v minutách - 2 hodiny
  //     'skill'      => "'f'",
  //     'autoskill'  => "''",
    );
    $kontakt= " V případě zjištění problému nebo <br/>potřeby konzultace mi prosím napište<br/>
          na mail&nbsp;<a href='mailto:{$EZER->options->mail}{$EZER->options->mail_subject}'>{$EZER->options->mail}</a> "
        . ($EZER->options->phone ? "případně zavolejte&nbsp;{$EZER->options->phone} " : '')
        . ($EZER->options->skype ? "nebo použijte Skype&nbsp;<a href='skype:{$EZER->options->skype}?chat'>{$EZER->options->skype}</a>" : '')
        . "<br/>Za spolupráci děkuje <br/>{$EZER->options->author}";
    $pars= (object)array(
      'watch_ip' => false,
      'watch_key' => false,
      'title_right' => $ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name,
      'contact' => $kontakt,
      'gc_maxlifetime'    => 12*60*60,            // životnost SESSION v sekundách - 12 hodin
      'CKEditor' => "{
        Minimal:{toolbar:[['Bold','Italic','Source']]},
        IntranetSlim:{
          toolbar:[['Bold','Italic','-','Subscript','Superscript','-','SpecialChar','Link','Unlink','-','Source']],
          removePlugins:'wsc,elementspath,scayt'
        }
      }"
    );
    $const= (object)array();
    root_php($app,$app_name,'test',$skin,$options,$js,$css,$pars,$const);
  }
?>
