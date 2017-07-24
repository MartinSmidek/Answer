<?php # (c) 2017 Martin Smidek <martin@smidek.eu>

# přehled o stavu portu Ezer2.2 na Ezer3
# používá utilitu CLOC viz https://github.com/AlDanial/cloc

# ------------------------------------------------------------------------------------- port
function port ($typ) {
  global $ezer_path_root;
  # funkcionalita  soubor2 řádků soubor3  řádků  %přenosu
  #         bloky  ezer.js  6425 ezer3.js  1105  12%      = 3/2+3
  $html= '';
  $tdr= "td style='text-align:right'";
  $thr= "th style='text-align:right'";
  $table= "table style='outline:1px solid black;background:antiquewhite'";
  $cloc= "$ezer_path_root/ezer3/cloc.exe";
  if ( !file_exists($cloc) ) { $html= "utilita CLOC není dostupná"; goto end; }

  $html.= "<h3>Stav přenosu klíčových částí jádra Ezer z verze 2.2 na verzi 3</h3>";

  // --------------------------------------------------------------- klient
  $html.= "<h3>Klient (Mootools => ES6+jQuery; HTML+CSS => HTML5+CSS3 )</h3>";
  $lines= array(
    array('část'=>'aplikace I','2'=>'app.js','3'=>''),
    array('část'=>'aplikace II','2'=>'ezer_fdom1.js','3'=>'ezer_app3.js'),
    array('část'=>'interpret ezerscriptu','2'=>'ezer.js','3'=>'ezer3.js'),
    array('část'=>'jednotlivé bloky','2'=>'ezer_fdom2.js','3'=>'ezer_fdom3.js'),
    array('část'=>'library','2'=>'lib.js','3'=>'ezer_lib3.js')
  );
  $js2= shell_exec("$cloc ezer2.2/client/*.js --json --by-file");
  $js2= json_decode($js2);
  $js3= shell_exec("$cloc ezer3/client/*.js --json --by-file");
  $js3= json_decode($js3);
//                                                       debug($js2);
  $html.= "<$table><tr style='box-shadow: 0 1px 0 #000'>
    <th>část</th>
      <th>verze 2.2</th><th>kód</th><th>(kód)</th>
      <th>verze 3</th><th>kód</th>
    <th>% přenosu</th><tr>";
  $c3_sum= 0;
  foreach ($lines as $line) {
    // orig. verze 2.2
    $f2= $line[2];
    $i2= "ezer2.2/client/$f2";
    $r2= $js2->$i2;
    $c2= $r2->code;
    $c2_sum+= $r2->code;
    // copy verze 2.2
    $ix= "ezer3/client/$f2";
    $rx= $js3->$ix;
    $cx= $rx->code;
    // verze 3
    $f3= $line[3];
    $i3= "ezer3/client/$f3";
    $r3= $js3->$i3;
    $c3= isset($r3) ? $r3->code : '-';
    $c3_sum+= isset($r3) ? $r3->code : 0;
    // tabulka
    $port= round(100 * $c3/($c2+$c3));
    $html.= "<tr><$thr>{$line['část']}</th>
      <td>$f2</td><$tdr>$c2</td><$tdr>$cx</td>
      <td>$f3</td><$tdr>$c3</td>
      <th>$port %</th><tr>";
  }
  $port= round(100 * $c3_sum/($c2_sum+$c3_sum));
  $html.= "<tr style='box-shadow: 0 -1px 0 #000'>
    <th>&sum;</th><td></td><td>$c2_sum</td><td></td><td></td><th>$c3_sum</th>
    <th>$port %</th><tr>";
  $html.= "</table>";

  // -------------------------------------------------------------- server
  $html.= "<h3>Server (PHP6 => PHP7; mysql => PDO)</h3>";
  $lines= array(
    array('část'=>'kompilátor ezerscriptu','2'=>'comp2.php','3'=>''),
    array('část'=>'ajax','2'=>'ezer2.php','3'=>'ezer3.php'),
    array('část'=>'library I','2'=>'ae_slib.php','3'=>'ezer_lib3.php'),
    array('část'=>'library II','2'=>'sys_doc.php','3'=>''),
    array('část'=>'library III','2'=>'reference.php','3'=>''),
  );
  $js2= shell_exec("$cloc ezer2.2/server/*.php --json --by-file");
  $js2= json_decode($js2);
  $js3= shell_exec("$cloc ezer3/server/*.php --json --by-file");
  $js3= json_decode($js3);
                                                      debug($js2);
  $html.= "<$table><tr style='box-shadow: 0 1px 0 #000'>
    <th>část</th>
      <th>verze 2.2</th><th>kód</th><th>(kód)</th>
      <th>verze 3</th><th>kód</th>
    <th>% přenosu</th><tr>";
  $c3_sum= 0;
  foreach ($lines as $line) {
    // orig. verze 2.2
    $f2= $line[2];
    $i2= "ezer2.2/server/$f2";
    $r2= $js2->$i2;
    $c2= $r2->code;
    $c2_sum+= $r2->code;
    // copy verze 2.2
    $ix= "ezer3/server/$f2";
    $rx= $js3->$ix;
    $cx= $rx->code;
    // verze 3
    $f3= $line[3];
    $i3= "ezer3/server/$f3";
    $r3= $js3->$i3;
    $c3= isset($r3) ? $r3->code : '-';
    $c3_sum+= isset($r3) ? $r3->code : 0;
    // tabulka
    $port= round(100 * $c3/($c2+$c3));
    $html.= "<tr><$thr>{$line['část']}</th>
      <td>$f2</td><$tdr>$c2</td><$tdr>$cx</td>
      <td>$f3</td><$tdr>$c3</td>
      <th>$port %</th><tr>";
  }
  $port= round(100 * $c3_sum/($c2_sum+$c3_sum));
  $html.= "<tr style='box-shadow: 0 -1px 0 #000'>
    <th>&sum;</th><td></td><td>$c2_sum</td><td></td><td></td><th>$c3_sum</th>
    <th>$port %</th><tr>";
  $html.= "</table>";

  $html.= "<br><br>Ve sloupcích <b>kód</b> je uváděn čistý počet řádků kódu (bez komentářů).";
  $html.= "<br>Sloupec <b>(kód)</b> udává \"zbytkový\" kód verze 2.2 ve verzi 3.";

end:
  return "<div style='background:white;margin:10px;padding:10px;'>$html</div>";
}

?>
