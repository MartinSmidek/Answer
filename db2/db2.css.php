<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
header("Content-type: text/css");
if ( !isset($_SESSION) ) session_start();
$ezer_root= isset($_GET['root']) ? $_GET['root'] : 'db2';
$skin= $_SESSION[$ezer_root]['skin'] ? $_SESSION[$ezer_root]['skin'] : 'default';
global $skin, $path, $c, $b, $ab, $c_appl,
  $c_menu, $b_menu, $c_main, $b_main,
  $c_group, $b_group, $s_group, $c_item, $b_item, $bd_item, $fb_item, $fc_item, $s_item, $s2_item,
  $b_brow, $b2_brow, $b3_brow, $b4_brow, $b5_brow, $b6_brow, $c_brow, $s1_brow, $s2_brow,
  $c_kuk, $c2_kuk, $b_kuk, $s_kuk, $b_doc_modul, $b_doc_menu, $b_doc_form,
  $b_parm, $b_work;
# c_=color, b_=background-color, a?_=aktivní, f?_=focus, s_=speciál
$abs_root=  $_SESSION[$ezer_root]['abs_root'];
require_once("$abs_root/skins/colors.php");

echo <<<__EOD
/* Android */
body { position:absolute; width:100%; height:100%; }
#paticka { bottom:0; }

/* zneviditelnění některých prvků gmap */
div.gmnoprint div.gm-style-mtc { display:none }

/* ladění */
.nogrid {
  background:$b /*url($path/ezer_layout.png)*/ !important; }

/* obecné barvy písma */
.bila    { color:#fff; }
.seda    { color:#777; }
.cervena { color:#f00; }
.zelena  { color:#0f0; }
.modra   { color:#00f; }
.cerna   { color:#000; }
.nic     { }
.skrtnuty { text-decoration:line-through; color:#f00 }
    
div.bila div.Label3 { color:#fff !important; }        

/* obecné barvy pozadí */
.bily           { background-color:#ffffff !important; }
.sedy           { background-color:#aaaaaa !important; }
.zluty          { background-color:#ffff77 !important; }
.cerveny        { background-color:#ff7777 !important; }
.oranzovy       { background-color:#ffaa00 !important; }
.zeleny         { background-color:#77ff77 !important; }
.modry          { background-color:#7389ae !important; }
.azurovy        { background-color:#77ffff !important; }
.fialovy        { background-color:#d27efc !important; }
.jemna          { background-color:$b_item; }
.bily_ram       { border: 1px solid #fff; border-radius: 5px; 
                  padding: 1px 6px; color:#fff !important; font-weight: bold; }
    
/* výběr access */

#appl {
  z-index: 99; }
#access {
  cursor: pointer; }
#access_menu {
  display:none; border: 1px solid #ccc; background: #fff;
  position: absolute; box-shadow:5px 5px 10px #567; cursor:default;
  color: black; font-size: 9pt; font-weight: normal; z-index: 99;
  width:150px; top: 40px; right: 10px; }
#access_menu span   {
  margin:0; padding: 2px 2px 2px 25px; display: block; }
#access_menu span.separator   {
  border-top:1px solid #999; }
#access_menu span:hover   {
  background-color:#b2b4bf; }

/* úpravy standardu - k Ezer3 */

textarea { resize:none; }    

/* specifické barvy pro odlišení záznamů YS, FA, DB a změny učiněné přes web*/
#appl { color:$zelena; }

tr td.ezer_ys, li.ezer_ys, input.ezer_ys, .ezer_ys input, span.ezer_ys
                { background-color:#af8 !important; }
tr td.ezer_ys_  { background-color:#af8 !important; color:#fff !important; }
tr td.ezer_fa, li.ezer_fa, input.ezer_fa, .ezer_fa input, span.ezer_fa
                { background-color:#acf !important; }
tr td.ezer_fa_  { background-color:#acf !important; color:#fff !important; }
tr td.ezer_db, li.ezer_db, input.ezer_db, .ezer_db input, span.ezer_db
                { background-color:#aff !important; }
.nerodina,.nerodina input
                { background-color:#e8e !important; }
tr td.ezer_web  { outline: 3px solid #ef7f13; }
tr td.ezer_web1 { border-left: 5px solid darkgrey; }
tr td.ezer_web2 { border-left: 5px solid red; }
/* */

button.search   { padding-left:0px; }
.search         { z-index:3 !important; }

/* */

.db_sep { margin-top:3px; width:100%; }
.db_hr  { border-top:1px solid $s1_brow; }
.db_evidence {  font-size:9px; background-color:$b_work; height:15px;
                color:$s1_brow; font-weight:bold; padding:0 2px; }
button.db_evidence:disabled { color:silver; }
.db_chain {  font-size:9px; background-color:$b_parm; height:15px;
             color:white; font-weight:bold; padding:0 2px; }
button.db_chain:disabled { color:silver; }
.curr_akce { background-color:$nazelenala !important; color:$bila; font-weight:bold; }
.form_note { font-size:10px; color:$s1_brow }
.title_ref { color:#ffffff !important; }
.title_ref a { color:#ffffff !important; }
.karta_info { background-color:$b8_brow; overflow: auto; }
.neucast  { text-decoration:line-through; color:#aaaaaa; }
.elim_goal { outline:3px solid $s1_brow; z-index:1 !important; }
#files, div.files { background-color:$b8_brow; overflow: auto; }
li.file { color:$zelena; text-decoration: underline; cursor:pointer; }

.shift_up   { z-index:1; }
.shift_up[class*=ae_frame],.shift_up[class*=ae_form2] {
              z-index:1; box-shadow:2px 2px 10px #333; }
.shift_down { z-index:0; }
.datepicker_vista { z-index:3 !important; }

.page { font-size:9pt !important; padding:10px; line-height:13pt; }
.page_2clmn { background-image:url(img/page_vr.png);
              background-position:368px 0;
              background-repeat:repeat-y;}
.page_3clmn { background-image:url(img/page_vr.png),url(img/page_vr.png);
              background-position:368px 0,619px 0;
              background-repeat:repeat-y,repeat-y;}

/* barvení časových změn ve formulářích */
.zmeneny, .zmeneny input {
  background-color:#ffaa00 !important; }
span.zmeneny {
  position:absolute; color:black; z-index:2; font-size:8px; height:11px; padding:0 1px;
  border-left:1px solid #aaa; border-right:1px solid #aaa; border-bottom:1px solid #aaa; }
/* úpravy standardu */
div.ContextHelp img {
  top:3px; padding:0; position:relative; }
div.Element3 .Label3, div.Select3 .Label3, div.FieldDate3 .Label3,            /*Ezer3*/
div.Element .Label, div.Select .Label, div.FieldDate .Label { margin-top:1px }
.BrowseSmart td.BrowseQry input {
  background-color:$b8_brow; }
.Panel {
  width:100%; }
/*.SelectDrop li {
  white-space:normal !important; }*/
.Label3, .Check3, .Case3,                                                        /* Ezer3*/
.Label, .Check, .Case {
  color:#456; font-size:11px; }
.Case input {
  vertical-align:sub; }
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
  cursor:default; background-color:$b_work; z-index:0; border-radius:5px; text-align:center;  
  height:13px !important; border-bottom: 10px solid $b_work; }
.ae_butt_off {
  cursor:default; background-color:$b8_brow; z-index:0; border-radius:5px; text-align:center;  
  height:13px !important; border-bottom: 10px solid $b8_brow; }
.ae_butt_on:hover { border-bottom-color:#ef7f13 !important; }
.ae_butt_off:hover { border-bottom-color:#ef7f13 !important; }
/* přenesení barev */
.frame_label_big { color:$s1_brow; font-weight:bold; padding-left:5px; font-size:13px; }
.frame_label_up { color:$s1_brow; font-weight:bold; padding-left:5px; }
.frame_label_dn { color:white;    font-weight:bold; padding-left:5px; }
/* rámečky formulářů */
.ae_info        {
  background-color:#f5f5f5; border:1px solid #f5f5f5; z-index:-1;
  border-radius:5px; }
.ae_work        {
  background-color:$b_work; z-index:0;
  border-radius:5px; }
.ae_form        {
  background-color:$b_work; z-index:0; border:1px solid $s1_brow; color:$s1_brow;
  border-radius:3px; text-indent:7px; font-weight:bold; }
.ae_form2       {
  background-color:$b_work; z-index:0; border:1px solid white; color:white;
  border-radius:3px; text-indent:7px; font-weight:bold; }
.form_switch    {
  background-color:$b_work; cursor:default; font-weight:bold; }
.ae_part        {
  background-color:$b_part; border:1px solid $s1_brow; z-index:0;
  border-radius:5px;  }
.ae_part_label {
  background-color:$b_part; color:$s1_brow; padding:0 5px; z-index:0; }
.ae_parm        {
  background-color:$b_parm; border:1px solid #f5f5f5; z-index:0;
  border-radius:5px; }
.ae_frame       {
  background-color:$b_work; z-index:0; border:1px solid $s1_brow; border-radius:3px;  }
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
table.stat      { border-collapse:collapse; font-size:8pt; /*width:100%;*/ }
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
