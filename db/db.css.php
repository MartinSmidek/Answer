<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
header("Content-type: text/css");
session_start();
$skin= $_SESSION['skin'] ? $_SESSION['skin'] : 'default';
global $skin, $path, $c, $b, $ab, $c_appl,
  $c_menu, $b_menu, $c_main, $b_main,
  $c_group, $b_group, $s_group, $c_item, $b_item, $fb_item, $fc_item, $s_item, $s2_item,
  $b_brow, $b2_brow, $b3_brow, $b4_brow, $b5_brow, $b6_brow, $c_brow, $s1_brow, $s2_brow,
  $c_kuk, $c2_kuk, $b_kuk, $s_kuk, $b_doc_modul, $b_doc_menu, $b_doc_form,
  $b_parm, $b_work;
# c_=color, b_=background-color, a?_=aktivní, f?_=focus, s_=speciál

require_once("../skins/colors.php");

echo <<<__EOD
/* úpravy standardu */
/*.BrowseSmart td.BrowseQry input {
  background-color:#effdf1; }*/
.SelectDrop li {
  white-space:normal !important; }
.Label, .Check {
  color:#456; }
.Label h1 {
  font-size:12pt; margin:0; padding:0 }
#Content, #Index {
  padding:15px; }
.CheckT input {
  position:absolute; top:10px; left:-3px; }
.ae_inp         { position:absolute; }
/*.inAccordion    { width:670px; }*/
/* prvky v akordeon-menu */
.zIndex1        { z-index:1; }
.info           { min-height:545px; height:100%; z-index:0; }
.info-stat      { width:624px; height:100%; z-index:0; background-color:#dce7f4; padding:5px; }
/* Label jako přepínací tlačítko */
.ae_butt_on {
  cursor:default; background-color:$b_work; z-index:0; /*behavior:url(ck/border-radius-ie8.htc);*/
  -moz-border-radius:5px; -webkit-border-radius:5px; -khtml-border-radius:5px; }
.ae_butt_off {
  cursor:default; background-color:#effdf1; z-index:0; /*behavior:url(ck/border-radius-ie8.htc);*/
  -moz-border-radius:5px; -webkit-border-radius:5px; -khtml-border-radius:5px; }
.ae_butt_on:hover {
  background:url("../../skins/ck/label_switch_on_hover.png") repeat-x scroll 0 -1px transparent !important; }
.ae_butt_off:hover {
  background:url("../../skins/ck/label_switch_off_hover.png") repeat-x scroll 0 -1px transparent !important; }
/* rámečky formulářů */
.ae_info        {
  background-color:#f5f5f5; border:1px solid #f5f5f5; z-index:-1; /*behavior:url(ck/border-radius-ie8.htc);*/
  -moz-border-radius:5px; -webkit-border-radius:5px; -khtml-border-radius:5px; }
.ae_work        {
  background-color:$b_work; z-index:0; /*behavior:url(ck/border-radius-ie8.htc);*/
  -moz-border-radius:5px; -webkit-border-radius:5px; -khtml-border-radius:5px; }
.ae_parm        {
  background-color:$b_parm; border:1px solid #f5f5f5; z-index:0;  /*behavior:url(ck/border-radius-ie8.htc);*/
  -moz-border-radius:5px; -webkit-border-radius:5px; -khtml-border-radius:5px;  }
/* barvení řádků browse */
.nakursu        { background-color:#dfa66f !important; }
.fis_red        { background-color:#933; }
.kas_1          { background-color:#ff6 !important; }
.dar_1          { background-color:#fbb !important; }
.dar_2          { background-color:#ffa !important; }
.vyp_n          { background-color:#fdd !important; }
.vyp_r          { background-color:#dfd !important; }
.vyp_p          { background-color:#ddf !important; }
.pre_5          { background-color:#ffffaa !important; }
.pre_7          { background-color:#7ff6ff !important; }
.pre_9          { background-color:#77ff77 !important; }
.sedy           { background-color:#777777 !important; }
.zluty          { background-color:#ffff77 !important; }
.cerveny        { background-color:#ff7777 !important; }
.zeleny         { background-color:#77ff77 !important; }
.modry          { background-color:#7389ae !important; }
/* */
.aktivni        { background-color:#ffeed6 !important; border:1px solid #ffeed6 !important; }
.pasivni        { background-color:#f5f5f5 !important; border:1px solid #f5f5f5 !important; }
.chyba          { color:#700; font-weight: bold; }
/* grafy */
.graphs         { background-color:#fff !important; left:10px; }
.graph          { margin-bottom:20px; }
.graph_title    { background-color:#D1DDEF; padding-left:10px; }
/* tabulky */
dd              { margin-left:20px; }
table.vypis     { border-collapse:collapse; width:100%; }
table.dary      { width:200px; }
.vypis th       { border:1px solid #777; background-color:#eee; }
.vypis td       { border:1px solid #777; }
.chart          { float:right; }
.fis_kat        { font-family:Courier; font-weight:bold; }
.button_small   { width:20px; height:20px; font-size:x-small; }
/* = = = = = = = = = = = = = = = = = = = = = = statistika */
div.stat        { padding-right:15px; }
table.stat      { border-collapse:collapse; font-size:8pt; width:100%; }
.stat td        { border:1px solid #777; background-color:#fff; padding:0 3px;}
.stat th        { border:1px solid #777; background-color:$b_item; }
.stat dt        { margin:10px 0 0 0; }
#proj table     { border-collapse:collapse; }
#proj td        { border:1px solid #aaa;font:x-small Arial;color:#777;padding:0 3px;}
#proj td.title  { color:#33a;}
#proj td.label  { color:#a33;}
.odhad          { background-color:#ffdab9 !important; }
/* = = = = = = = = = = = = = = = = = = = = = = rozesilani */
table.roze      { border-collapse:collapse; }
.roze td        { border:1px solid #777; text-align:right; background-color:#eee; padding:3px; color:black;}
.roze th        { border:1px solid #777; text-align:right; background-color:#aaa; }

__EOD;
?>
