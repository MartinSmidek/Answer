<?php
$r= rand(0,9);
$g= rand(0,9);
$b= rand(0,9);
$color= "#$r$g$b";
header("Content-type: text/css");
echo <<<__EOD
.PanelRight {
  background-color:$color !important;
}
__EOD;
?>
