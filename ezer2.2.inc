<?php # Systém CK/Ezer2,
# (c) 2010 Martin Šmídek <martin@smidek.eu>

$ezer_root= 'ezer2.2';                           // jméno adresáře a hlavního objektu aplikace

// nastavení verze jádra
$EZER= (object)array();
$EZER->version= isset($_GET['ezer']) ? "ezer{$_GET['ezer']}" : 'ezer2.2';

require_once("$ezer_root/server/ae_slib.php");

// OBECNÉ PARAMETRY

// parametry s první hodnotou pro server a druhou (případně) pro local
$db= array('ezer_ck','ezer_ck');
$dbs= array(
  array(),
  array(                                              // vývojová instalace
   'ezer_ys'          => array(0,'localhost','gandi','','utf8'),
   'ezer_system'      => array(0,'localhost','gandi','','utf8','ezer_ys')
 )
);

// nastavení databází
$ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru
$sada= $ezer_local ? 1 : 0;
// $mysql_db= $db[$sada];
$ezer_db= $dbs[$sada];

// moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
$ezer_comp_ezer= "app,ezer,area,ezer_report,ezer_fdom1,ezer_fdom2";
$ezer_comp_root= "";

// moduly v Ezerscriptu mimo složku aplikace
$ezer_ezer= array();

?>
