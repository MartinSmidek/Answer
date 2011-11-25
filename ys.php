<?php # Systém An(w)er/Ezer2, (c) 2008-2010 Martin Šmídek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  $app=      'ys';
  $app_name= 'Ans(w)er';
  $skin=     'default';
  $skin=     'ck';

  // Ans(w)er rozeznává tyto doplňkové parametry v URL
  //   dbs=db_name:other_db_name,...    -- záměna MySQL tabulek za jiné (je zpracováváno ve fis.ini)
  require_once("$app.inc");
  require_once("ezer2/server/ae_slib.php");
  $app_name.= $EZER->options->mysql ? " - {$EZER->options->mysql}" : '';

  $js= array(
    'ezer2/client/licensed/ckeditor/ckeditor.js',
    'ezer2/client/licensed/clientcide.js',
    'ezer2/client/licensed/mootools/asset.js',
    'ezer2/client/licensed/mootools/slider.js',
    'ezer2/client/licensed/glfx.js',
    'ezer2/client/lib.js',
    'ezer2/client/ezer_fdom1.js',
    'ezer2/client/ezer.js',
    'ezer2/client/ezer_report.js',
    'ezer2/client/ezer_fdom2.js',
    'ezer2/client/app.js',
    'db/db_fce.js',
    'ds/fce.js'
  );
  $css= array(
    './ezer2/client/ezer.css.php',
    './ezer2/client/licensed/fancyupload.css',
    './ys/ys.css.php',
    './db/db.css.php'
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
    'watch_key' => true,               // true = povolit přístup jen po vložení klíče
    'watch_ip' => true,                // true = povolit přístup jen ze známých IP adres
    'title_right' => $ezer_local ? "<span style='color:#ef7f13'>$app_name</span>" : $app_name,
    'contact' => $kontakt
  );
  root_php($app,$app_name,'news',$skin,$options,$js,$css,$pars);

?>
