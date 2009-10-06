<?php # (c) 2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- eu
# na UTF8 na stránce win1250 v tabulce
function eu($x) { #trace();
  $y= str_replace('\u011b','ě',$x);
  return $y;
}
# -------------------------------------------------------------------------------------------------- wu
# na UTF8 na stránce win1250 v tabulce
function wu($x,$user2sql=0) { #trace();
  if ( $user2sql ) {
    // převeď uživatelskou podobu na sql tvar
    $y= utf2win($x,true);
  }
  else {
    // převeď sql tvar na uživatelskou podobu (default)
    $y= win2utf($x,true);
  }
  return $y;
}
# -------------------------------------------------------------------------------------------------- dt
# na datum na stránce z timestamp v tabulce
function dt($x,$user2sql=0) { #trace();
  if ( $user2sql ) {
    // převeď uživatelskou podobu na sql tvar
    $y= win2utf($x,true);
  }
  else {
    // převeď sql tvar na uživatelskou podobu (default)
    $y= date("j.n.Y", $x);
  }
  return $y;
}
# -------------------------------------------------------------------------------------------------- postit
function postit($host, $url, $postdata) {
  $fp= fsockopen ($host, 80, &$errno, &$errstr, 60);
  if( $fp ) {
    fputs ($fp,"POST $url HTTP/1.1\n");
    fputs ($fp,"Host: $host\n");
//     fputs ($fp,"User-Agent: Autopost demonstration script\n");
//     fputs ($fp,"Accept: */*\n");
    fputs ($fp,"Content-type: application/x-www-form-urlencoded\n");
    fputs ($fp,"Content-length: ".strlen($postdata)."\n\n");
    fputs ($fp,"\n\n$postdata\n\n");
    $n= 0;
    $output= "$n:";
    $header= true;
    while( !feof( $fp ) ) {
      $n++;
      $line.= fgets($fp);
      if ($line == "\n\n"  )
        $output.= "<hr>";
  //       $header= false;
  //     else
        $output.= "<br>$n:$line";
    }
    fclose ( $fp);
  }
  else
    $output= "$errstr ($errno)<br>\n";
  return $output;
}
# -------------------------------------------------------------------------------------------------- file_post_sock
# http://php.vrana.cz/nacitani-souboru.php   modified
function file_post_sock($url,$data) {
  global $json,$EZER,$aaa;
    $url = parse_url($url);

    if (!isset($url['port'])) {
      if ($url['scheme'] == 'http') { $url['port']=80; }
      elseif ($url['scheme'] == 'https') { $url['port']=443; }
    }
    $url['query']=isset($url['query'])?$url['query']:'';

    $url['protocol']=$url['scheme'].'://';
    $eol="\r\n";
//     $encdata= json_encode($data);
//     $encdata= $json->encode($data);
    // zakódování
    $send= ""; $del= "";
    foreach ($data as $key => $val) {
      $send.= "$del$key=$val";
      $del= '&';
    }
    $headers =  "POST ".$url['protocol'].$url['host'].$url['path']." HTTP/1.1".$eol.
                "Host: ".$url['host'].$eol.
                "Referer: ".$url['protocol'].$url['host'].$url['path'].$eol.
                "Content-Type: application/x-www-form-urlencoded".$eol.
//                 "Content-Type: application/json".$eol.
                "Content-Length: ".strlen($send).$eol.
                "Connection: Close".
                $eol.$eol.
                $send;
//                 "json=".$encdata;
    $aaa= $headers;
    $fp = pfsockopen($url['host'], $url['port'], $errno, $errstr, 30);
    if($fp) {
      fputs($fp, $headers);
      $result = '';
      while(!feof($fp)) {
        $result = fgets($fp, 128);
      }
      fclose($fp);
        $pattern="/^.*\r\n\r\n/s";
        $result=preg_replace($pattern,'',$result);
      return $result;
    }
}
# ================================================================================================== ch
# -------------------------------------------------------------------------------------------------- ch_load_ivan
# import oficiálního seznamu
function ch_smtp($x) {  #trace();
  global $json,$EZER,$aaa;
//   $answer= postit("ys2.ezer",$EZER->options->smtp,"nekolik slov");
  $url= "{$EZER->options->smtp}?co=neco&kdo=ne kdo";
  $answer= file_post_sock($url,array('co'=>'alfa','kdo'=>'běda'));
  return eu("$url<hr>$aaa<hr>$answer");
}
# -------------------------------------------------------------------------------------------------- ch_load_ivan
# import oficiálního seznamu
function ch_load_ivan() {  #trace();
  global $ezer_path_root;
  $fname= "$ezer_path_root/ch/data/iniciace_adresare.csv";
  $f= fopen($fname, "r");
  if ( $f ) {
    $html.= "Import ze souboru $fname ... ";
    $line= 0;
    $values= ''; $del= '';
    while (($data= fgetcsv($f, 1000, ";")) !== false) {
      $line++;
      if ( $line<4 ) continue; // vynechání hlaviček
      $psc= str_replace(" ",'',$data[1]);
      $values.= "$del (\"{$data[0]}\",\"$psc\",\"$data[2]\",\"$data[3]\",\"$data[4]\",\"$data[5]\",\"$data[6]\")";
      $del= ',';
    }
    $html.= "ok <br>";
    fclose($f);
    // smazání starých
    $qry= "TRUNCATE mrop;";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "starý seznam smazán, ";
      // vložení nového
      $values= win2utf($values,true);
      $qry= "INSERT INTO mrop (jmeno,psc,mesto,adresa,telefon,email,rocnik) VALUES $values;";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "vloženo $n účastníků<br>";
    }
  }
  else fce_error("importní soubor $fname neexistuje");
//   $html.= nl2br("<br>qry=\n$qry<br>");
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ch_duplicita_ivan
# zjištění duplicitních emailových adres
function ch_duplicita_ivan() {  #trace();
  $html= '';
  $qry= "SELECT email, left(GROUP_CONCAT(jmeno),100) as lst
         FROM mrop WHERE email!='' GROUP BY email HAVING (count(email)>1)";
  $res= mysql_qry($qry);
  while ( $res && $o= mysql_fetch_object($res) ) {
    $html.= "<br>email <b>{$o->email}</b> je používán více chlapy: {$o->lst}";
  }
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ch_duplicita_www
# zjištění duplicitních jmen
function ch_duplicita_www() {  #trace();
  $html= '';
  $qry= "SELECT username, left(GROUP_CONCAT(name),100) as lst
         FROM setkani.fe_users WHERE email!='' GROUP BY username HAVING (count(username)>1)";
  $res= mysql_qry($qry);
  while ( $res && $o= mysql_fetch_object($res) ) {
    $html.= "<br>uživatelské jméno <b>{$o->username}</b> je používáno více chlapy: {$o->lst}";
  }
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ch_kod
# doplnění přístupu na stránky
function ch_kod($rok=0) {  #trace();
  $rok= $rok?$rok:date('Y');
  $html= '';
  $obsazeno= "";
  $first_id= $n= 0;
  // návrh username pro www
  $qry= "SELECT * FROM mrop WHERE rocnik=$rok AND www_user='' ";
  $res= mysql_qry($qry);
  while ( $res && $o= mysql_fetch_object($res) ) {
    list($email)= explode(',',$o->email);
    if ( $email ) {
      list($name)= explode('@',$email);
      $qrys= "SELECT count(*) as x FROM setkani.fe_users WHERE username='$name' ";
      $ress= mysql_qry($qrys);
      if ( $ress && ($s= mysql_fetch_object($ress)) && $s->x ) {
        $obsazeno.= " $name";
      }
      else {
        $qr= "UPDATE mrop SET www_user='$name' WHERE id_mrop={$o->id_mrop}";
        $re= mysql_qry($qr);
        // vložení do fe_users
        $pass= $name.rand(1,999);
        list($sname,$fname)= explode(' ',$o->jmeno);
        $crdate= time();
//     $novy[]= array('email'=>'jarsychra@email.cz','psc'=>'256 01','obec'=>'Benešov','ulice'=>'Vlašimská 1793','tel'=>'604 602 132','prijmeni'=>'Sychra','jmeno'=>'Jaroslav');
        $html.= "<br>
    \$novy[]= array('email'=>'$email','psc'=>'{$o->psc}','obec'=>'{$o->mesto}','ulice'=>'{$o->adresa}','tel'=>'{$o->telefon}','prijmeni'=>'$sname','jmeno'=>'$fname');";
        $n++;
//         $qr= "INSERT INTO setkani.fe_users (
//                 username,password,usergroup,firstname,name,address,telephone,email,
//                 crdate,zip,city,userlevel,note)
//               VALUES (
//                 '$name','$pass','4,6','$fname','$sname','{$o->adresa}','{$o->telefon}','{$o->email}',
//                 $crdate,'{$o->psc}','{$o->mesto}',1,'mrop=$rok')";
//         $html.= "<br>$qr";
//         $re= mysql_qry($qr);
//         $n+= mysql_affected_rows();
//         if ( !$first_id ) $first_id= mysql_insert_id();
      }
    }
  }
  $html.= "<br><br>je zobrazen kód pro $n uživatelů<br><br>již obsazená jména: $obsazeno";
  $result= (object)array('html'=>$html);
  return $result;
}
?>
