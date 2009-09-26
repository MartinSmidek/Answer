<?php # (c) 2009 Martin Smidek <martin@smidek.eu>
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
?>
