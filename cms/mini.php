<?php
/** ==========================================================================================> EZER */
function wu($x) { return $x; }
# -------------------------------------------------------------------------------------------- trace
# $note je poznámka uvedená za trasovací informací
function trace($note='',$coding='') {
  global $trace, $totrace;
  if ( strpos($totrace,'u')===false ) return;
  $time= date("H:i:s");
  $act= debug_backtrace();
  $x= "$time ".call_stack($act,1).($note?" / $note":'');
//   if ( $coding=='win1250' ) $x= wu($x);
  $x= mysql_real_escape_string($x);
  $trace.= "<script>console.log( 'Trace: " . $x . "' );</script>";
}
function display($x) {
  global $trace, $totrace;
  if ( strpos($totrace,'u')===false ) return;
  $x= mysql_real_escape_string($x);
  $trace.= "<script>console.log( 'Display: " . $x . "' );</script>";
}
function debug($x,$label=false,$options=null) {
  global $trace, $totrace;
  if ( strpos($totrace,'u')===false ) return;
  $x= mysql_real_escape_string(var_export($x,true));
  $trace.= "<script>console.log( \"" . str_replace("\n",'\n',$x) . "\" );</script>";
}
function fce_error($x) {
  global $trace;
  $x= mysql_real_escape_string($x);
  $trace.= "<script>console.log( 'ERROR: " . $x . "' );</script>";
}
# --------------------------------------------------------------------------------------- call_stack
function call_stack($act,$n,$hloubka=2,$show_call=1) { #$this->debug($act,'call_stack');
  $fce= isset($act[$n]['class'])
    ? "{$act[$n]['class']}{$act[$n]['type']}{$act[$n]['function']}" : $act[$n]['function'];
  $del= '';
  $max_string= 36;
  $args= '';
  if ( $show_call and isset($act[$n]['args']) )
  foreach ( $act[$n]['args'] as $arg ) {
    if ( is_string($arg) ) {
      $arg= mb_substr(htmlspecialchars($arg,ENT_NOQUOTES,'UTF-8'),0,$max_string)
          .(mb_strlen($arg)>$max_string?'...':'');
    }
    $typ= gettype($arg);
    $val= '';
    switch ( $typ ) {
    case 'boolean': case 'integer': case 'double': case 'string': case 'NULL':
      $val= $arg; break;
    case 'array':
      $val= count($arg); break;
    case 'object':
      $val= get_class($arg); break;
    }
    $args.= "$del$typ:$val";
    $del= ',';
  }
  $from= '';
  for ($k= $n; $k<$n+$hloubka; $k++) {
    if ( isset($act[$k]) )
    switch ( key($act[$k]) ) {
    case 'file':
      $from_file= str_replace('.php','',$act[$k]['file']);
      $from.= " < ".substr(strrchr($from_file,'\\'),1);
      $from.= "/{$act[$k]['line']}";
      break;
    case 'function':
      $from.= " < ".($act[$k]['class']?"{$act[$k]['class']}.":'').$act[$k]['function'];
      break;
    default:
      $from.= " < ? ";
      break;
    }
  }
  return $show_call ? "$fce($args)$from" : $from;
}
# ------------------------------------------------------------------------------------- array2object
function array2object(array $array) {
  $object = new stdClass();
  foreach($array as $key => $value) {
    if(is_array($value)) {
      $object->$key = array2object($value);
    }
    else {
      $object->$key = $value;
    }
  }
  return $object;
}
# ------------------------------------------------------------------------------------- ezer_connect
# spojení s databází
# $db = jméno databáze uvedené v konfiguraci aplikace
# $db = .main. pokud má být připojena první databáze z konfigurace
# $initial=1 pokud není ještě aktivní fce_error
function ezer_connect ($db0='.main.',$even=false,$initial=0) {
  global $ezer_db, $EZER;
  $err= '';
  $db= $db0;
  if ( $db=='.main.' ) {
    foreach ( $ezer_db as $db1=>$desc) {
      $db= $db1;
      break;
    }
  }
  // vlastní připojení, pokud nebylo ustanoveno
  $db_name= (isset($ezer_db[$db][5]) && $ezer_db[$db][5]!='') ? $ezer_db[$db][5] : $db;
  if ( !$ezer_db[$db][0] || $even ) {
    $ezer_db[$db][0]= @mysql_pconnect($ezer_db[$db][1],$ezer_db[$db][2],$ezer_db[$db][3]);
    if ( !$ezer_db[$db][0] ) {
      fce_error("db=$db|connect: server '{$ezer_db[$db][1]}' s databazi '"
        . ($ezer_db[$db][5] ? "$db/$db_name" : $db)."' neni pristupny:").mysql_error();
    }
  }
  $res= @mysql_select_db($db_name,$ezer_db[$db][0]);
  if ( !$res ) {
    $ok= 0;
    $err= "databaze '$db_name' je nepristupna";
    if ( !$initial ) fce_error("connect: $err".mysql_error());
    else die("connect: $err".mysql_error());
  }
  if ( $ezer_db[$db][4] ) {
    mysql_query("SET NAMES '{$ezer_db[$db][4]}'");
  }
  return $err;
}
# ---------------------------------------------------------------------------------------- mysql_row
# provedení dotazu v $y->qry="..." a vrácení mysql_fetch_assoc (případně doplnění $y->err)
function mysql_row($qry,$err=null) {
  $res= mysql_qry($qry,1);
  $row= $res ? mysql_fetch_assoc($res) : array();
  if ( !$res ) mysql_err($qry);
  return $row;
}
# ------------------------------------------------------------------------------------- mysql_object
# provedení dotazu v $y->qry="..." a vrácení mysql_fetch_object (případně doplnění $y->err)
function mysql_object($qry,$err=null) {
  $res= mysql_qry($qry,1);
  $x= $res ? mysql_fetch_object($res) : array();
  if ( !$res ) mysql_err($qry);
  return $x;
}
# ------------------------------------------------------------------------------------- getmicrotime
function getmicrotime() {
//   list($usec, $sec) = explode(" ", microtime());
//   return ((float)$usec + (float)$sec);
  return round(microtime(true)*1000);
}
# ---------------------------------------------------------------------------------------- mysql_err
# ošetření chyby a doplnění $y->error, $y->ok
function mysql_err($qry) {
  global $y;
  $msg= '';
  $merr= mysql_error();
  $serr= "You have an error in your SQL";
  if ( $merr && substr($merr,0,strlen($serr))==$serr ) {
    $msg.= "SQL error ".substr($merr,strlen($serr))." in:$qry";
  }
  else {
    $myerr= $err ? $err : $merr;
    $myerr= str_replace('"',"U",$myerr);
    $msg.= win2utf("\"$myerr\" ")."\nQRY:$qry";
  }
  $y->ok= 'ko';
  fce_error($msg);
}
# ---------------------------------------------------------------------------------------- mysql_qry
# provedení dotazu a textu v $y->qry="..." a případně doplnění $y->err
#   $qry      -- SQL dotaz
#   $pocet    -- pokud je uvedeno, testuje se a při nedodržení se ohlásí chyba
#   $err      -- text chybové hlášky, která se použije místo standardní ... pokud končí znakem':'
#                bude za ni doplněna standardní chybová hláška;
#                pokud $err=='-' nebude generována chyba a funkce vrátí false
#   $to_throw -- chyba způsobí výjimku
#   $db       -- před dotazem je přepnuto na databázi daného jména v tabulce $ezer_db nebo na hlavní
function mysql_qry($qry,$pocet=null,$err=null,$to_throw=false,$db='') {
  global $trace, $y, $totrace, $qry_del, $qry_count, $ezer_db;
  if ( !isset($y) ) $y= (object)array();
  $msg= ''; $abbr= '';
  $qry_count++;
  $myqry= str_replace('"',"U",$qry);
//                                                         display($myqry);
  // dotaz s měřením času
  $time_start= getmicrotime();
  // přepnutí na databázi
  if ( $db ) ezer_connect($db);
  $res= @mysql_query($qry);
  $time= round(getmicrotime() - $time_start,4);
  $ok= $res ? 'ok' : '--';
  if ( !$res ) {
    if ( $err=='-' ) goto end;
    $merr= mysql_error();
    $serr= "You have an error in your SQL";
    if ( $merr && substr($merr,0,strlen($serr))==$serr ) {
      $msg.= "SQL error ".substr($merr,strlen($serr))." in:$qry";
      $abbr= '/S';
    }
    else {
      $myerr= $merr;
      if ( $err ) {
        $myerr= $err;
        if ( substr($err,-1,1)==':' )
          $myerr.= $merr;
      }
//       $myerr= str_replace('"',"U",$myerr);
      $msg.= "\"$myerr\" \nQRY:$qry";
      $abbr= '/E';
    }
    $y->ok= 'ko';
  }
  // pokud byl specifikován očekávaný počet, proveď kontrolu
  else if ( $pocet  ) {
    if ( substr($qry,0,6)=='SELECT' )
      $num= mysql_num_rows($res);
    elseif ( in_array(substr($qry,0,6),array('INSERT','UPDATE','REPLAC','DELETE')) )
      $num= mysql_affected_rows(); // INSERT, UPDATE, REPLACE or DELETE
    else
      fce_error("mysql_qry: neznámá operace v $qry");
    if ( $pocet!=$num ) {
      if ( $num==0 ) {
        $msg.= "nenalezen záznam " . ($err ? ", $err" : ""). " v $qry";
        $abbr= '/0';
      }
      else {
        $msg.= "vraceno $num zaznamu misto $pocet" . ($err ? ", $err" : ""). " v $qry";
        $annr= "/$num";
      }
      $y->ok= 'ko';
      $ok= "ko [$num]";
      $res= null;
    }
  }
  if ( strpos($totrace,'M')!==false ) {
    $qry= mysql_real_escape_string((isset($y->qry)?"\n":'')."$ok $time \"$myqry\" ");
    $trace.= "<script>console.log( \"SQL: $qry \");</script>";
  }
  $y->qry_ms= isset($y->qry_ms) ? $y->qry_ms+$time : $time;
  $qry_del= "\n: ";
  if ( $msg ) {
    if ( $to_throw ) throw new Exception($err ? "$err$abbr" : $msg);
    else fce_error((isset($y->error) ? $y->error : '').$msg);
  }
end:
  return $res;
}
# ------------------------------------------------------------------------------------------- select
# navrácení hodnoty jednoduchého dotazu
# pokud $expr obsahuje čárku, vrací pole hodnot, pokud $expr je hvězdička vrací objekt
# příklad 1: $id= select("id","tab","x=13")
# příklad 2: list($id,$x)= select("id,x","tab","x=13")
function select($expr,$table,$cond=1,$db='.main.') {
  if ( strstr($expr,",") ) {
    $result= array();
    $qry= "SELECT $expr FROM $table WHERE $cond";
    $res= mysql_qry($qry,0,0,0,$db);
    if ( !$res ) fce_error("chyba funkce select:$qry/".mysql_error());
    $result= mysql_fetch_row($res);
  }
  elseif ( $expr=='*' ) {
    $qry= "SELECT * FROM $table WHERE $cond";
    $res= mysql_qry($qry,0,0,0,$db);
    if ( !$res ) fce_error(wu("chyba funkce select:$qry/".mysql_error()));
    $result= mysql_fetch_object($res);
  }
  else {
    $result= '';
    $qry= "SELECT $expr AS _result_ FROM $table WHERE $cond";
    $res= mysql_qry($qry,0,0,0,$db);
    if ( !$res ) fce_error(wu("chyba funkce select:$qry/".mysql_error()));
    $o= mysql_fetch_object($res);
    $result= $o->_result_;
  }
//                                                 debug($result,"select");
  return $result;
}
# ------------------------------------------------------------------------------------------ select1
# navrácení hodnoty jednoduchého dotazu - $expr musí vracet jednu hodnotu
function select1($expr,$table,$cond=1,$db='.main.') {
  $result= '';
  $qry= "SELECT $expr AS _result_ FROM $table WHERE $cond";
  $res= mysql_qry($qry,0,0,0,$db);
  if ( !$res ) fce_error(wu("chyba funkce select1:$qry/".mysql_error()));
  $o= mysql_fetch_object($res);
  $result= $o->_result_;
  return $result;
}
# ------------------------------------------------------------------------------------ select_object
# navrácení hodnot jednoduchého jednoznačného dotazu jako objektu (funkcí mysql_fetch_object)
function select_object($expr,$table,$cond=1,$db='.main.') {
  $qry= "SELECT $expr FROM $table WHERE $cond";
  $res= mysql_qry($qry,0,0,0,$db);
  if ( !$res ) fce_error(wu("chyba funkce select_object:$qry/".mysql_error()));
  $result= mysql_fetch_object($res);
  return $result;
}
# -------------------------------------------------------------------------------------------- query
# provedení MySQL dotazu
function query($qry,$db='.main.') {
  $res= mysql_qry($qry,0,0,0,$db);
  if ( !$res ) fce_error(wu("chyba funkce query:$qry/".mysql_error()));
  return $res;
}
# ---------------------------------------------------------------------------------------- sql_query
# provedení MySQL dotazu
function sql_query($qry,$db='.main.') {
  $obj= (object)array();
  $res= mysql_qry($qry,0,0,0,$db);
  if ( $res ) {
    $obj= mysql_fetch_object($res);
  }
  return $obj;
}
# ---------------------------------------------------------------------------------------- sql_date1
// datum bez dne v týdnu
function sql_date1 ($datum,$user2sql=0,$del='.') {
  if ( $user2sql ) {
    // převeď uživatelskou podobu na sql tvar
    $text= '';
    if ( $datum ) {
      $datum= str_replace(' ','',$datum);
      list($d,$m,$y)= explode('.',$datum);
      $text= $y.'-'.str_pad($m,2,'0',STR_PAD_LEFT).'-'.str_pad($d,2,'0',STR_PAD_LEFT);
    }
  }
  else {
    // převeď sql tvar na uživatelskou podobu (default)
    $text= '';
    if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
      $y=substr($datum,0,4);
      $m=substr($datum,5,2);
      $d=substr($datum,8,2);
      //$h=substr($datum,11,2);
      //$n=substr($datum,14,2);

      $text.= date("j{$del}n{$del}Y",strtotime($datum));
//      $text.= "$d.$m.$y";
//                                                 display("$datum:$text");
    }
  }
  return $text;
}
# ----------------------------------------------------------------------------------------- sql_date
// datum
function sql_date ($datum,$user2sql=0) {
  if ( $user2sql ) {
    // převeď uživatelskou podobu na sql tvar
    $text= '';
    if ( $datum ) {
      $datum= trim($datum);
      list($d,$m,$y)= explode('.',$datum);
      $text= $y.'-'.str_pad($m,2,'0',STR_PAD_LEFT).'-'.str_pad($d,2,'0',STR_PAD_LEFT);
    }
  }
  else {
    // převeď sql tvar na uživatelskou podobu (default)
    $dny= array('ne','po','út','st','čt','pá','so');
    $text= '';
    if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
      $y= 0+substr($datum,0,4);
      $m= 0+substr($datum,5,2);
      $d= 0+substr($datum,8,2);
      //$h=substr($datum,11,2);
      //$n=substr($datum,14,2);
      $t= mktime(0,0,1,$m,$d,$y)+1;
//                                                 display("$datum:$m,$d,$y:$text:$t");
      $text= $dny[date('w',$t)];
      $text.= " $d.$m.$y";
    }
  }
  return $text;
}
?>
