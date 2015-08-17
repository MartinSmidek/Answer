<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
# definice barev v závislosti na zvoleném skinu
# jméno skinu je i jménem podsložky s obrázky pro CSS
global $skin, $path, $c, $b, $ab, $c_appl,
  $c_menu, $b_menu, $c_main, $b_main,
  $c_group, $b_group, $s_group, $c_item, $b_item, $bd_item, $fb_item, $fc_item, $s_item, $s2_item,
  $b_brow, $b2_brow, $b3_brow, $b4_brow, $b5_brow, $b6_brow, $c_brow, $s1_brow, $s2_brow,
  $c_kuk, $c2_kuk, $b_kuk, $s_kuk, $b_doc_modul, $b_doc_menu, $b_doc_form;
# c_=color, b_=background-color, a?_=aktivní, f?_=focus, s_=speciál
switch ($skin) {
case 'db': // --------------------------------------------------------- db: barvy společného Answeru
  $path= "../../skins/$skin";                     // cesta k background-image
  $bila= '#ffffff'; $cerna= '#000000';            // základní barvy
  // barvy specifické pro styl
  $nasedla= '#e6e6e6'; $seda= '#4d4d4d';
  $cervena= '#a90533'; $oranzova= '#ef7f13';  $lososova= '#F0E2C2';
  $zelena= '#317676'; $nazelenala= '#299C9C'; $zelenkava= '#C2E2E2'; $zelenoucka= '#F1FCFD';
  // prvky - musí být v global
  $c= $cerna; $b= $nasedla; $ab= $bila;
  $c_appl= $zelena;
  $c_menu= $bila; $b_menu= $seda;
  $c_main= $zelena; $b_main= $seda;
  $c_group= $bila; $b_group= $nazelenala; $s_group= $seda;
  $c_item= $seda; $b_item= $zelenkava; $bd_item= '#ddd'; $fb_item= $oranzova; $fc_item= $bila;
    $s_item= $s2_item= $seda;
//   $b_brow= '#ccc'; $b2_brow= $lososova; $b3_brow= $bila; $b4_brow= $zelenkava;
  $b_brow= '#ccc'; $b2_brow= $bila; $b3_brow= $bila; $b4_brow= $nasedla;
    $b5_brow= $nasedla; $b6_brow= $nasedla; $b7_brow= $zelenkava; $b8_brow= $zelenoucka;
    $c_brow= $seda; $s1_brow= $nazelenala; $s2_brow= $cervena;
  $c_kuk= $zelena; $c2_kuk= $bila; $c3_kuk= $cerna; $b_kuk= $oranzova; $s_kuk= $oranzova;
  $b_warn= '#adff2f'; $c_warn= '#000000';
  $b_doc_modul= $oranzova; $b_doc_menu= $zelena; $b_doc_form= $zelena;
  $b_parm= $oranzova; $b_part= $zelenkava; $b_work= $zelenkava;
  // úpravy ezer.css.php
  $w_right= 750;        // šířka panel.right
  break;
case 'ck': // --------------------------------------------------------- ck: barvy webu www.hospic.cz
  $path= "../../skins/$skin";                     // cesta k background-image
  $bila= '#ffffff'; $cerna= '#000000';            // základní barvy
  // barvy specifické pro styl
  $nasedla= '#e6e6e6'; $seda= '#4d4d4d';
  $cervena= '#a90533'; $oranzova= '#ef7f13';  $lososova= '#F0E2C2';
  $zelena= '#2c8931'; $nazelenala= '#56a15a'; $zelenkava= '#C0E2C2'; $zelenoucka= '#EFFDF1';
  // prvky - musí být v global
  $c= $cerna; $b= $nasedla; $ab= $bila;
  $c_appl= $zelena;
  $c_menu= $bila; $b_menu= $seda;
  $c_main= $zelena; $b_main= $seda;
  $c_group= $bila; $b_group= $nazelenala; $s_group= $seda;
  $c_item= $seda; $b_item= $zelenkava; $bd_item= '#ddd'; $fb_item= $oranzova; $fc_item= $bila;
    $s_item= $s2_item= $seda;
//   $b_brow= '#ccc'; $b2_brow= $lososova; $b3_brow= $bila; $b4_brow= $zelenkava;
  $b_brow= '#ccc'; $b2_brow= $bila; $b3_brow= $bila; $b4_brow= $nasedla;
    $b5_brow= $nasedla; $b6_brow= $nasedla; $b7_brow= $zelenkava; $b8_brow= $zelenoucka;
    $c_brow= $seda; $s1_brow= $nazelenala; $s2_brow= $cervena;
  $c_kuk= $zelena; $c2_kuk= $bila; $c3_kuk= $cerna; $b_kuk= $oranzova; $s_kuk= $oranzova;
  $b_warn= '#adff2f'; $c_warn= '#000000';
  $b_doc_modul= $oranzova; $b_doc_menu= $zelena; $b_doc_form= $zelena;
  $b_parm= $oranzova; $b_part= $zelenkava; $b_work= $zelenkava;
  // úpravy ezer.css.php
  $w_right= 750;        // šířka panel.right
  break;
case 'ch': // --------------------------------------------------------- ch: barvy webu www.chlapi.cz
  $path= "../../skins/$skin";                     // cesta k background-image
  $bila= '#ffffff'; $cerna= '#000000';            // základní barvy
  // barvy specifické pro styl
  $nasedla= '#e6e6e6'; $seda= '#4d4d4d';
  $cervena= '#a90533'; $oranzova= '#ef7f13';  $lososova= '#F0E2C2';
  $zelena= '#2c4989'; $nazelenala= '#365faf'; $zelenkava= '#c0cae2'; $zelenoucka= '#EFF1FD';
  // prvky - musí být v global
  $c= $cerna; $b= $nasedla; $ab= $bila;
  $c_appl= $zelena;
  $c_menu= $bila; $b_menu= $seda;
  $c_main= $zelena; $b_main= $seda;
  $c_group= $bila; $b_group= $nazelenala; $s_group= $seda;
  $c_item= $seda; $b_item= $zelenkava; $bd_item= '#ddd'; $fb_item= $oranzova; $fc_item= $bila;
    $s_item= $s2_item= $seda;
//   $b_brow= '#ccc'; $b2_brow= $lososova; $b3_brow= $bila; $b4_brow= $zelenkava;
  $b_brow= '#ccc'; $b2_brow= $bila; $b3_brow= $bila; $b4_brow= $nasedla;
    $b5_brow= $nasedla; $b6_brow= $nasedla; $b7_brow= $zelenkava; $b8_brow= $zelenoucka;
    $c_brow= $seda; $s1_brow= $nazelenala; $s2_brow= $cervena;
  $c_kuk= $zelena; $c2_kuk= $bila; $c3_kuk= $cerna; $b_kuk= $oranzova; $s_kuk= $oranzova;
  $b_warn= '#eef2ae'; $c_warn= '#000000';
  $b_doc_modul= $oranzova; $b_doc_menu= $zelena; $b_doc_form= $zelena;
  $b_parm= $oranzova; $b_part= $zelenkava; $b_work= $zelenkava;
  // úpravy ezer.css.php
  $w_right= 750;        // šířka panel.right
  break;
case 'default': // ---------------------------------------------------- default barvy podle Office2007
  $path= "./skins/default";                       // cesta k background-image
  $bila= '#ffffff'; $cerna= '#000000'; $seda= '#4d4d4d'; $zelena= '#2c8931'; // základní barvy
  // prvky
  $c= '#333'; $b= '#6f93c3'; $ab= $bila;
  $c_appl= $bila;
  $c_menu= 'navy'; $b_menu= $seda;
  $c_main= $cerna; $b_main= $bila;
  $c_group= $bila; $b_group= '#7389ae'; $s_group= '#5e708e';
  $c_item= '#333'; $b_item= '#cde'; $bd_item= '#ccc'; $fb_item= '#3e4043'; $fc_item= '#faec8f';
    $s_item= '#9ab'; $s2_item= '#303234';
  $b_brow= '#ccc'; $b2_brow= '#f2f8ff'; $b3_brow= '#E5E5E6'; $b4_brow= '#d1e4ff';
    $b5_brow= '#f0f0f0'; $b6_brow= '#f2f8ff'; $b7_brow= '#d1e4ff'; $b8_brow= '#effdf1';
    $c_brow= '#777'; $s1_brow= '#6593cf'; $s2_brow= '#d30';
  $c_kuk= 'navy'; $c2_kuk= $c_kuk; $b_kuk= '#fb6'; $s_kuk= '#FBC84F';
  $b_warn= $s_kuk; $c_warn= '#000000';
  $b_doc_modul= '#c17878'; $b_doc_menu= '#7389ae'; $b_doc_form= '#80a2cf';
  $b_parm= '#ffeed6'; $b_part= '#93b3de'; $b_work= '#93b3de';
  break;
}
?>
