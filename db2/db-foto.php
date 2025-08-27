<?php

/** ==========================================================================================> FOTO */
# funkce pro práce se seznamem fotek:
#   set=přidání na konec, get=vrácení n-té, del=smazání n-té a vrácení n-1 nebo n+1
# ---------------------------------------------------------------------------------------- foto2_get
# vrátí n-tou fotografii ze seznamu (-1 znamená poslední) spolu s informacemi:
# ret =>
#   html  = kód pro zobrazení miniatury (s href na původní velikost) nebo chybové hlášení
#   left  = pořadí předchozí fotky nebo 0
#   right = pořadí následující fotky nebo 0
# ? out   = seznam s vynecháním
function foto2_get($table,$id,$n,$w,$h) {  trace();
  global $ezer_path_root;
  $ret= (object)array('img'=>'','left'=>0,'right'=>0,'msg'=>'','tit'=>'','nazev'=>'');
  $fotky= '';
  // názvy fotek
  $osobnich= 0;
  if ( $table=='rodina' ) {
    list($fotky,$rodinna)= select('fotka,nazev','rodina',"id_$table=$id");
  }
  elseif ( $table=='osoba' ) {
    $rf= pdo_qry("
      SELECT GROUP_CONCAT(r.fotka) AS fotky,r.nazev,
        o.fotka,o.prijmeni,o.jmeno
      FROM rodina AS r JOIN tvori USING (id_rodina) JOIN osoba AS o USING (id_osoba)
      WHERE id_osoba=$id AND r.fotka!='' AND role IN ('a','b') ");
    $x= pdo_fetch_object($rf);
    $rodinna= $x->nazev;
    $fotky= $x->fotky;
    $fotka= $x->fotka;
    if ( $fotka ) {
      $osobnich= substr_count($fotka,',')+1;
      $osobni= "{$x->prijmeni} {$x->jmeno}";
      $fotky= $fotky ? "$fotka,$fotky" : $fotka;
    }
  }
  if ( $fotky=='' ) {
    $ret->html= "žádná fotka";
    $ret->jmeno= '';
    goto end;
  }
  $nazvy= explode(',',$fotky);
//            debug($nazvy,"rodina $rodinna");
  // název n-té fotky
  $n= $n==-1 ? count($nazvy) : $n;
//                                         display("fotky='$fotky', n=$n");
  if ( !(1<=$n && $n<=count($nazvy)) ) { $ret->html= "$n je chybné pořadí fotky"; goto end; }
  // výpočet left, right, out
  $ret->left= $n-1;
  $ret->right= $n >= count($nazvy) ? 0 : $n+1;
  // zpracování
  $nazev= $nazvy[$n-1];
  $orig= "$ezer_path_root/fotky/$nazev";
//                                         display("file_exists($orig)=".file_exists($orig));
  if ( !file_exists($orig) ) {
    $ret->html= "fotka <b>$nazev</b> není dostupná";
    goto end;
  }
  // zmenšení na požadovanou velikost, pokud již není
  $dest= "$ezer_path_root/fotky/copy/$nazev";
  if ( !file_exists($dest) ) {
    $ok= foto2_resample($orig,$dest,$w,$h,0,1);
    if ( !$ok ) { $ret->html= "fotka </b>$nazev</b> nešla zmenšit ($ok)"; goto end; }
  }
  // html-kód s žádostí o zaostření na straně klienta
  $ret->nazev= $nazev;
  $jmeno= $n>$osobnich ? $rodinna : $osobni;
  $ret->jmeno= "<span style='font-weight:bold;font-size:120%'>$jmeno</span>";
//                                                 display("$n>$osobnich ? $rodinna : $osobni");
  $stamp= "?x=".time();
  $ret->html= "<a href='fotky/$nazev' target='_album' title='$jmeno ($nazev)'>
    <img src='fotky/copy/$nazev$stamp'
      onload='var x=arguments[0];img_filter(x.target,\"sharpen\",0.7,1);'/></a>";
end:
//                                                 debug($ret,"album_get2($table,$id,$n,$w,$h)");
  return $ret;
}
# ---------------------------------------------------------------------------------------- foto2_add
# přidá fotografii do seznamu (rodina|osoba) podle ID na konec a vrátí její index
# vrátí 0, pokud fotka s tímto jménem již existuje
function foto2_add($table,$id,$name) { trace();
  // přidání názvu fotky do záznamu v tabulce
  $n= 0;
  $f= trim(select('fotka',$table,"id_$table=$id"));
  $fotky= explode(',',$f);
  // vrátí 0, pokud fotka s tímto jménem již existuje
  if (in_array($name,$fotky)) return $n;
  // jinak fotku přidej
  if ($f) $fotky[]= $name; else $fotky= array($name);
  $n= count($fotky);
  $fotky= implode(',',$fotky);
  query("UPDATE $table SET fotka='$fotky' WHERE id_$table=$id");
  return $n;
}
# ------------------------------------------------------------------------------------- foto2_delete
# zruší n-tou fotografii ze seznamu v albu a vrátí pořadí následující nebo předchozí nebo 0
function foto2_delete($table,$id,$n) { trace();
  global $ezer_path_root;
  $ret= (object)array('ok'=>0,'n'=>0);
  // nalezení seznamu názvů fotek
  $fotky= explode(',',select('fotka',$table,"id_$table=$id"));
  if ( 1<=$n && $n<=count($fotky) ) {
    $nazev= $fotky[$n-1];
    unset($fotky[$n-1]);
    $nazvy= implode(',',$fotky);
    query("UPDATE $table SET fotka='$nazvy' WHERE id_$table=$id");
    // smazání fotky a miniatury
    $ret->ok= unlink("$ezer_path_root/fotky/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/$name')=$ok");
    $ret->ok&= unlink("$ezer_path_root/fotky/copy/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/copy/$name')=$ok");
  }
  // vrať nějakou nesmazanou nebo 0
  $ret->n= $n>1 ? $n-1 : (count($fotky) ? 1 : 0);
  return $ret;
}
# ------------------------------------------------------------------------------------- foto2 rotate
# otočení obrázku, deg=90|180|270
function foto2_rotate($nazev,$deg) {
  global $ezer_path_root;
  $src= "$ezer_path_root/fotky/$nazev";
  $ok= foto2_rotate_abs($src,$deg);
  if ( !$ok ) goto end;
  $src= "$ezer_path_root/fotky/copy/$nazev";
  $ok= foto2_rotate_abs($src,$deg);
end:  
  return $ok;
}
# ------------------------------------------------------------------------------------- foto2 rotate
# otočení obrázku, deg=90|180|270
function foto2_rotate_abs($src,$deg) { trace();
  $err= '';
  $ok= 0;
  $part= pathinfo($src);
  $ext= strtolower($part['extension']);
//  $ysrc= "{$part['dirname']}/{$part['filename']}_$deg.$ext";
  $ysrc= $src; // inplace
  ini_set('memory_limit', '512M');
  switch ($ext) {
  case 'jpg':
    if ( !file_exists($src) )  { $err= "$src nelze nalezt"; goto end; }
    $img= imagecreatefromjpeg($src);
    if ( !$img ) { $err= "$src nema format JPEG"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagejpeg($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  case 'png':
    $img= imagecreatefrompng($src);
    if ( !$img ) { $err= "$src nema format PNG"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagepng($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  case 'gif':
    $img= imagecreatefromgif($src);
    if ( !$img ) { $err= "$src nema format GIF"; goto end; }
    $img= imagerotate($img,$deg,0);
    if ( !imagegif($img,$ysrc) ) { $err= "$ysrc nelze ulozit"; goto end; }
    $ok= 1;
    break;
  default:
    $err= "neznamy typ obrazku '$src'";
  }
end:
  if ( $err ) {
    fce_warning($err); 
    $ok= 0;
  }
  return $ok;
}
# ----------------------------------------------------------------------------------- foto2_resample
function foto2_resample($source, $dest, &$width, &$height,$copy_bigger=0,$copy_smaller=1) { #trace();
  global $CONST;
  $maxWidth= $width;
  $maxHeight= $height;
  $ok= 1;
  // zjistime puvodni velikost obrazku a jeho typ: 1 = GIF, 2 = JPG, 3 = PNG
  list($origWidth, $origHeight, $type)=@ getimagesize($source);
//                                                 debug(array($origWidth, $origHeight, $type),"album_resample($source, $dest, &$width, &$height,$copy_bigger)");
  if ( !$type ) $ok= 0;
  if ( $ok ) {
    if ( !$maxWidth ) $maxWidth= $origWidth;
    if ( !$maxHeight ) $maxHeight= $origHeight;
    // nyni vypocitam pomer změny
    $pw= $maxWidth / $origWidth;
    $ph= $maxHeight / $origHeight;
    $p= min($pw, $ph);
    // vypocitame vysku a sirku změněného obrazku - vrátíme ji do výstupních parametrů
    $newWidth = (int)($origWidth * $p);
    $newHeight = (int)($origHeight * $p);
    $width= $newWidth;
    $height= $newHeight;
//                                                 display("p=$p, copy_smaller=$copy_smaller");
    if ( ($pw == 1 && $ph == 1) || ($copy_bigger && $p<1) || ($copy_smaller && $p>1) ) {
//                                                 display("kopie");
      // jenom zkopírujeme
      copy($source,$dest);
    }
    else {
//                                                 display("úprava");
      // zjistíme velikost cíle - abychom nedělali zbytečnou práci
      $destWidth= $destHeight= -1; $ok= 2; // ok=2 -- nic se nedělalo
      if ( file_exists($dest) ) list($destWidth, $destHeight)= getimagesize($dest);
      if ( $destWidth!=$newWidth || $destHeight!=$newHeight ) {
        // vytvorime novy obrazek pozadovane vysky a sirky
        $image_p= ImageCreateTrueColor($newWidth, $newHeight);
        // otevreme puvodni obrazek se souboru
        switch ($type) {
        case 1: $image= ImageCreateFromGif($source); break;
        case 2: $image= ImageCreateFromJpeg($source); break;
        case 3: $image= ImageCreateFromPng($source); break;
        }
        // okopirujeme zmenseny puvodni obrazek do noveho
        if ( $maxWidth || $maxHeight )
          ImageCopyResampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        else
          $image_p= $image;
        // ulozime
        $ok= 0;
        switch ($type) {
        case 1: /*ImageColorTransparent($image_p);*/ $ok= ImageGif($image_p, $dest);  break;
        case 2: $ok= ImageJpeg($image_p, $dest);  break;
        case 3: $ok= ImagePng($image_p, $dest);  break;
        }
      }
    }
  }
  return $ok;
}
