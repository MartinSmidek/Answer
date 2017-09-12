<?php # (c) 2017 Martin Smidek <martin@smidek.eu>
# ===========================================================================================> TESTY
# ----------------------------------------------------------------------------------------- tut3 inc
# test
function tut3_inc($i) { trace();
  sleep(1);
                                                        display("tit3_inc i=$i");
  return $i+1;
}
# ----------------------------------------------------------------------------------------- tut3 tst
# test
function tut3_tst($i) { trace();
  $html= "zrušit uživatele";
  return $html;
}
# =====================================================================================> SELECT AUTO
# ---------------------------------------------------------------------------------------- test_auto
# test autocomplete
function test_auto($patt,$par) {  trace();
//                                                       debug($par,"test_auto.par");
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadejte vzor";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT id_jmena AS _key,jmeno AS _value
           FROM _jmena
           WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
                                                        display("test_auto:$qry");
    $res= mysql_qry($qry);
    while ( $res && $t= mysql_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $a->{$t->_key}= $t->_value;
    }
                                                        display("test_auto:$n,$limit");
    // obecné položky
    if ( !$n )
      $a->{0}= "... nic nezačíná $patt";
    elseif ( $n==$limit )
      $a->{999998}= "... a další";
  }
                                                      debug($a,"test_auto");
  return $a;
}
?>
