<?php # (c) 2014 Martin Smidek <martin@smidek.eu>
header("Content-type: text/css");
session_start();
$skin= $_SESSION['skin'] ? $_SESSION['skin'] : 'default';
global $skin, $path, $c, $b, $ab, $c_appl,
  $c_menu, $b_menu, $c_main, $b_main,
  $c_group, $b_group, $s_group, $c_item, $b_item, $bd_item, $fb_item, $fc_item, $s_item, $s2_item,
  $b_brow, $b2_brow, $b3_brow, $b4_brow, $b5_brow, $b6_brow, $c_brow, $s1_brow, $s2_brow,
  $c_kuk, $c2_kuk, $b_kuk, $s_kuk, $b_doc_modul, $b_doc_menu, $b_doc_form,
  $b_parm, $b_work;
# c_=color, b_=background-color, a?_=aktivní, f?_=focus, s_=speciál

require_once("../skins/colors.php");

echo <<<__EOD

/* styly pro wait */

#wait_mask {
  display:none;
  opacity: 0.9; background-color: rgba(51, 51, 51, 0.08);
  padding:30px; position:absolute; z-index:996; width:100%; height:100%; }
#wait {
  margin:auto; width:64px; height:64px; z-index:997; background-color:transparent;
  top: 0; bottom: 0; left: 0; right: 0; position: absolute;
  background-image:url(img/spinner.gif); }

/* Tutorial - Label jako přepínací tlačítko */
.page_on {
  cursor:default; background-color:$b_work; z-index:0; border-radius:5px; text-align:center; }
.page_off {
  cursor:default; background-color:$b8_brow; z-index:0; border-radius:5px; text-align:center; }
.page_on:hover {
  background:url("../../skins/ck/label_switch_on_hover.png") repeat-x scroll 0 -1px transparent; }
.page_off:hover {
  background:url("../../skins/ck/label_switch_off_hover.png") repeat-x scroll 0 -1px transparent; }

.ae_work {
  background-color:$b_work; z-index:0; border-radius:5px; }
.ae_parm        {
  background-color:$b_parm; border:1px solid #f5f5f5; z-index:0; border-radius:5px; }

__EOD;
?>
