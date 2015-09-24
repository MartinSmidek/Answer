<?php # Systém Answer/Ezer, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Archa pro kompilaci jádra Ezer2.2

  // nastavení verze jádra
  $EZER= (object)array();
  $EZER->version= 'ezer2.2';

  // identifikace serveru
  $server_name= isset($_SERVER["HTTP_X_FORWARDED_SERVER"])
                ? $_SERVER["HTTP_X_FORWARDED_SERVER"]
                : $_SERVER["SERVER_NAME"];
  $ezer_local= preg_match('/^\w+\.ezer/',$server_name); // identifikace ladícího serveru

  // databáze ezer_kernel s první hodnotou pro server a druhou (případně) pro local
  $dbs= array(
    array( /* ostré */
      'ezer_kernel'   => array(0,'localhost','gandi','r8d0st','utf8')
    ),
    array( /* lokální */
      'ezer_kernel'   => array(0,'localhost','gandi','','utf8')
    )
  );
  // nastavení databází
  $sada= $ezer_local ? 1 : 0;
  $ezer_db= $dbs[$sada];

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,area,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "";

?>
